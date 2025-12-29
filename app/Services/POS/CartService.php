<?php

namespace App\Services\POS;

use App\Models\Product;
use Illuminate\Support\Facades\Session;

class CartService
{
    protected $sessionKey = 'pos_cart';

    public function addItem($productId, $quantity = 1)
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
        } else {
            $cart[$productId] = [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'price' => $product->selling_price,
                'quantity' => $quantity,
                'subtotal' => $product->selling_price * $quantity,
                'stock' => $product->stock,
                'image' => $product->image_url,
            ];
        }

        $this->saveCart($cart);

        return $cart[$productId];
    }

    public function updateItem($productId, $quantity)
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
        foreach ($cart as &$item) {
            $item['product'] = Product::find($item['id']);
        }

        return array_values($cart);
    }

    public function getCartSummary()
    {
        $cart = $this->getCart();

        $totalItems = 0;
        $subtotal = 0;

        foreach ($cart as $item) {
            $totalItems += $item['quantity'];
            $subtotal += $item['subtotal'];
        }

        return [
            'total_items' => $totalItems,
            'subtotal' => $subtotal,
            'tax' => 0, // Will be calculated separately
            'discount' => 0, // Will be applied separately
            'grand_total' => $subtotal,
        ];
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
}
