<?php

namespace App\Services\POS;

use App\Models\Product;
use Illuminate\Support\Facades\Session;

class CartService
{
    protected $sessionKey = 'pos_cart';
    protected $cartKey = 'cart_items';
    protected $customerKey = 'cart_customer';

    public function addItem($productId, $quantity = 1, $discount = 0)
    {
        $product = Product::findOrFail($productId);

        // Check stock availability
        if ($product->stock < $quantity) {
            throw new \Exception("Stok {$product->name} tidak mencukupi. Stok tersedia: {$product->stock}");
        }

        $cart = $this->getCart();

        if (isset($cart[$productId])) {
            $newQuantity = $cart[$productId]['quantity'] + $quantity;

            // Check if new quantity exceeds stock
            if ($newQuantity > $product->stock) {
                throw new \Exception("Stok {$product->name} tidak mencukupi untuk jumlah ini");
            }

            $cart[$productId]['quantity'] = $newQuantity;
            $cart[$productId]['subtotal'] = $newQuantity * $cart[$productId]['price'];
            $cart[$productId]['discount'] = $discount;
            $cart[$productId]['total'] = $cart[$productId]['subtotal'] - $discount;
        } else {
            $subtotal = $product->selling_price * $quantity;
            $total = $subtotal - $discount;

            $cart[$productId] = [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'price' => $product->selling_price,
                'quantity' => $quantity,
                'discount' => $discount,
                'subtotal' => $subtotal,
                'total' => $total,
                'stock' => $product->stock,
                'image' => $product->image_url,
                'unit' => $product->unit?->short_name,
                'category' => $product->category?->name,
            ];
        }

        $this->saveCart($cart);

        return $cart[$productId];
    }

    public function updateItem($productId, $quantity, $discount = null)
    {
        $cart = $this->getCart();

        if (!isset($cart[$productId])) {
            throw new \Exception('Produk tidak ditemukan dalam keranjang');
        }

        $product = Product::findOrFail($productId);

        // Check stock availability
        if ($product->stock < $quantity) {
            throw new \Exception("Stok {$product->name} tidak mencukupi. Stok tersedia: {$product->stock}");
        }

        if ($quantity <= 0) {
            return $this->removeItem($productId);
        }

        $cart[$productId]['quantity'] = $quantity;
        $cart[$productId]['subtotal'] = $quantity * $cart[$productId]['price'];

        if ($discount !== null) {
            $cart[$productId]['discount'] = $discount;
        }

        $cart[$productId]['total'] = $cart[$productId]['subtotal'] - $cart[$productId]['discount'];

        $this->saveCart($cart);

        return $cart[$productId];
    }

    public function updateDiscount($productId, $discount)
    {
        $cart = $this->getCart();

        if (!isset($cart[$productId])) {
            throw new \Exception('Produk tidak ditemukan dalam keranjang');
        }

        if ($discount < 0 || $discount > $cart[$productId]['subtotal']) {
            throw new \Exception('Diskon tidak valid');
        }

        $cart[$productId]['discount'] = $discount;
        $cart[$productId]['total'] = $cart[$productId]['subtotal'] - $discount;

        $this->saveCart($cart);

        return $cart[$productId];
    }

    public function removeItem($productId)
    {
        $cart = $this->getCart();

        if (isset($cart[$productId])) {
            unset($cart[$productId]);
            $this->saveCart($cart);
        }

        return true;
    }

    public function clearCart()
    {
        Session::forget($this->sessionKey);
        Session::forget($this->cartKey);
        Session::forget($this->customerKey);
        return true;
    }

    public function getCart()
    {
        return Session::get($this->sessionKey, []);
    }

    public function getCartItems()
    {
        $cart = $this->getCart();

        // Add product details for each item
        $items = [];
        foreach ($cart as $item) {
            $product = Product::find($item['id']);
            if ($product) {
                $item['product'] = $product;
                $item['current_stock'] = $product->stock;
                $items[] = $item;
            }
        }

        return $items;
    }

    public function getCartSummary($taxRate = 0, $serviceCharge = 0)
    {
        $cart = $this->getCart();

        $totalItems = 0;
        $subtotal = 0;
        $totalDiscount = 0;

        foreach ($cart as $item) {
            $totalItems += $item['quantity'];
            $subtotal += $item['subtotal'];
            $totalDiscount += $item['discount'];
        }

        $tax = $subtotal * ($taxRate / 100);
        $service = $subtotal * ($serviceCharge / 100);
        $grandTotal = $subtotal - $totalDiscount + $tax + $service;

        return [
            'total_items' => $totalItems,
            'subtotal' => $subtotal,
            'total_discount' => $totalDiscount,
            'tax_rate' => $taxRate,
            'tax_amount' => $tax,
            'service_charge_rate' => $serviceCharge,
            'service_charge' => $service,
            'grand_total' => $grandTotal,
        ];
    }

    public function setCustomer($customerId)
    {
        Session::put($this->customerKey, $customerId);
        return true;
    }

    public function getCustomer()
    {
        $customerId = Session::get($this->customerKey);
        return $customerId ? \App\Models\Customer::find($customerId) : null;
    }

    public function clearCustomer()
    {
        Session::forget($this->customerKey);
        return true;
    }

    private function saveCart($cart)
    {
        Session::put($this->sessionKey, $cart);
    }

    public function validateStockAvailability()
    {
        $cart = $this->getCart();
        $errors = [];

        foreach ($cart as $productId => $item) {
            $product = Product::find($productId);

            if (!$product) {
                $errors[] = "Produk ID {$productId} tidak ditemukan";
                continue;
            }

            if ($product->stock < $item['quantity']) {
                $errors[] = "Stok {$product->name} tidak mencukupi. Stok tersedia: {$product->stock}, Diminta: {$item['quantity']}";
            }
        }

        return $errors;
    }

    public function applyGlobalDiscount($discountType, $discountValue)
    {
        $cart = $this->getCart();

        if ($discountType === 'percentage') {
            if ($discountValue < 0 || $discountValue > 100) {
                throw new \Exception('Diskon persentase harus antara 0-100%');
            }

            foreach ($cart as $productId => &$item) {
                $discountAmount = ($item['subtotal'] * $discountValue) / 100;
                $item['discount'] = $discountAmount;
                $item['total'] = $item['subtotal'] - $discountAmount;
            }
        } elseif ($discountType === 'amount') {
            $totalSubtotal = array_sum(array_column($cart, 'subtotal'));

            if ($discountValue > $totalSubtotal) {
                throw new \Exception('Diskon tidak boleh lebih besar dari subtotal');
            }

            // Distribute discount proportionally
            foreach ($cart as $productId => &$item) {
                $proportion = $item['subtotal'] / $totalSubtotal;
                $discountAmount = $discountValue * $proportion;
                $item['discount'] = $discountAmount;
                $item['total'] = $item['subtotal'] - $discountAmount;
            }
        } else {
            throw new \Exception('Tipe diskon tidak valid');
        }

        $this->saveCart($cart);

        return $cart;
    }

    public function getCartCount()
    {
        $cart = $this->getCart();
        return count($cart);
    }
}
