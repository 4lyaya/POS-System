<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['guest'])->group(function () {
    Route::get('/login', function () {
        return Inertia::render('Auth/Login');
    })->name('login');

    Route::get('/register', function () {
        return Inertia::render('Auth/Register');
    })->name('register');
});

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    
    // Profile
    Route::get('/profile', [App\Http\Controllers\Auth\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [App\Http\Controllers\Auth\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [App\Http\Controllers\Auth\ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Product Management Routes
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', [App\Http\Controllers\ProductController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\ProductController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\ProductController::class, 'store'])->name('store');
        Route::get('/{product}', [App\Http\Controllers\ProductController::class, 'show'])->name('show');
        Route::get('/{product}/edit', [App\Http\Controllers\ProductController::class, 'edit'])->name('edit');
        Route::put('/{product}', [App\Http\Controllers\ProductController::class, 'update'])->name('update');
        Route::delete('/{product}', [App\Http\Controllers\ProductController::class, 'destroy'])->name('destroy');
        Route::post('/{product}/adjust-stock', [App\Http\Controllers\ProductController::class, 'adjustStock'])->name('adjust-stock');
        Route::get('/export/excel', [App\Http\Controllers\ProductController::class, 'export'])->name('export.excel');
        Route::post('/import/excel', [App\Http\Controllers\ProductController::class, 'import'])->name('import.excel');
    });
    
    // Category Routes
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [App\Http\Controllers\CategoryController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\CategoryController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\CategoryController::class, 'store'])->name('store');
        Route::get('/{category}', [App\Http\Controllers\CategoryController::class, 'show'])->name('show');
        Route::get('/{category}/edit', [App\Http\Controllers\CategoryController::class, 'edit'])->name('edit');
        Route::put('/{category}', [App\Http\Controllers\CategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [App\Http\Controllers\CategoryController::class, 'destroy'])->name('destroy');
    });
    
    // Unit Routes
    Route::prefix('units')->name('units.')->group(function () {
        Route::get('/', [App\Http\Controllers\UnitController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\UnitController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\UnitController::class, 'store'])->name('store');
        Route::get('/{unit}/edit', [App\Http\Controllers\UnitController::class, 'edit'])->name('edit');
        Route::put('/{unit}', [App\Http\Controllers\UnitController::class, 'update'])->name('update');
        Route::delete('/{unit}', [App\Http\Controllers\UnitController::class, 'destroy'])->name('destroy');
    });
    
    // Supplier Routes
    Route::prefix('suppliers')->name('suppliers.')->group(function () {
        Route::get('/', [App\Http\Controllers\SupplierController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\SupplierController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\SupplierController::class, 'store'])->name('store');
        Route::get('/{supplier}', [App\Http\Controllers\SupplierController::class, 'show'])->name('show');
        Route::get('/{supplier}/edit', [App\Http\Controllers\SupplierController::class, 'edit'])->name('edit');
        Route::put('/{supplier}', [App\Http\Controllers\SupplierController::class, 'update'])->name('update');
        Route::delete('/{supplier}', [App\Http\Controllers\SupplierController::class, 'destroy'])->name('destroy');
    });
    
    // Purchase Routes
    Route::prefix('purchases')->name('purchases.')->group(function () {
        Route::get('/', [App\Http\Controllers\PurchaseController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\PurchaseController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\PurchaseController::class, 'store'])->name('store');
        Route::get('/{purchase}', [App\Http\Controllers\PurchaseController::class, 'show'])->name('show');
        Route::get('/{purchase}/edit', [App\Http\Controllers\PurchaseController::class, 'edit'])->name('edit');
        Route::put('/{purchase}', [App\Http\Controllers\PurchaseController::class, 'update'])->name('update');
        Route::delete('/{purchase}', [App\Http\Controllers\PurchaseController::class, 'destroy'])->name('destroy');
        Route::post('/{purchase}/payment', [App\Http\Controllers\PurchaseController::class, 'addPayment'])->name('payment.add');
        Route::get('/{purchase}/receipt', [App\Http\Controllers\PurchaseController::class, 'receipt'])->name('receipt');
    });
    
    // POS & Sales Routes
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::get('/', [App\Http\Controllers\SaleController::class, 'index'])->name('index');
        Route::get('/pos', [App\Http\Controllers\SaleController::class, 'create'])->name('pos');
        Route::post('/', [App\Http\Controllers\SaleController::class, 'store'])->name('store');
        Route::get('/{sale}', [App\Http\Controllers\SaleController::class, 'show'])->name('show');
        Route::post('/{sale}/cancel', [App\Http\Controllers\SaleController::class, 'cancel'])->name('cancel');
        Route::get('/{sale}/receipt', [App\Http\Controllers\SaleController::class, 'receipt'])->name('receipt');
        Route::get('/{sale}/receipt/download', [App\Http\Controllers\SaleController::class, 'downloadReceipt'])->name('receipt.download');
        
        // Cart routes
        Route::post('/cart/add', [App\Http\Controllers\SaleController::class, 'addToCart'])->name('cart.add');
        Route::put('/cart/{productId}', [App\Http\Controllers\SaleController::class, 'updateCart'])->name('cart.update');
        Route::delete('/cart/clear', [App\Http\Controllers\SaleController::class, 'clearCart'])->name('cart.clear');
        Route::get('/cart', [App\Http\Controllers\SaleController::class, 'getCart'])->name('cart.get');
    });
    
    // Customer Routes
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [App\Http\Controllers\CustomerController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\CustomerController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\CustomerController::class, 'store'])->name('store');
        Route::get('/{customer}', [App\Http\Controllers\CustomerController::class, 'show'])->name('show');
        Route::get('/{customer}/edit', [App\Http\Controllers\CustomerController::class, 'edit'])->name('edit');
        Route::put('/{customer}', [App\Http\Controllers\CustomerController::class, 'update'])->name('update');
        Route::delete('/{customer}', [App\Http\Controllers\CustomerController::class, 'destroy'])->name('destroy');
    });
    
    // Stock Management Routes
    Route::prefix('stock')->name('stock.')->group(function () {
        Route::get('/', [App\Http\Controllers\StockController::class, 'index'])->name('index');
        Route::get('/adjustments', [App\Http\Controllers\AdjustmentController::class, 'index'])->name('adjustments.index');
        Route::get('/adjustments/create', [App\Http\Controllers\AdjustmentController::class, 'create'])->name('adjustments.create');
        Route::post('/adjustments', [App\Http\Controllers\AdjustmentController::class, 'store'])->name('adjustments.store');
        Route::get('/adjustments/{adjustment}', [App\Http\Controllers\AdjustmentController::class, 'show'])->name('adjustments.show');
        Route::get('/mutations', [App\Http\Controllers\StockController::class, 'mutations'])->name('mutations');
        Route::get('/low-stock', [App\Http\Controllers\StockController::class, 'lowStock'])->name('low-stock');
        Route::get('/expiring', [App\Http\Controllers\StockController::class, 'expiring'])->name('expiring');
    });
    
    // Report Routes
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [App\Http\Controllers\ReportController::class, 'index'])->name('index');
        Route::get('/sales', [App\Http\Controllers\ReportController::class, 'sales'])->name('sales');
        Route::get('/inventory', [App\Http\Controllers\ReportController::class, 'inventory'])->name('inventory');
        Route::get('/financial', [App\Http\Controllers\ReportController::class, 'financial'])->name('financial');
        Route::post('/export', [App\Http\Controllers\ReportController::class, 'export'])->name('export');
        Route::get('/dashboard-stats', [App\Http\Controllers\ReportController::class, 'dashboardStats'])->name('dashboard.stats');
        Route::get('/sales-chart', [App\Http\Controllers\ReportController::class, 'salesChart'])->name('sales.chart');
    });
    
    // User Management Routes (Admin only)
    Route::middleware(['role:admin'])->prefix('users')->name('users.')->group(function () {
        Route::get('/', [App\Http\Controllers\UserController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\UserController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\UserController::class, 'store'])->name('store');
        Route::get('/{user}', [App\Http\Controllers\UserController::class, 'show'])->name('show');
        Route::get('/{user}/edit', [App\Http\Controllers\UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [App\Http\Controllers\UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [App\Http\Controllers\UserController::class, 'destroy'])->name('destroy');
        Route::post('/{user}/toggle-status', [App\Http\Controllers\UserController::class, 'toggleStatus'])->name('toggle-status');
    });
    
    // Role Management Routes (Admin only)
    Route::middleware(['role:admin'])->prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [App\Http\Controllers\RoleController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\RoleController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\RoleController::class, 'store'])->name('store');
        Route::get('/{role}/edit', [App\Http\Controllers\RoleController::class, 'edit'])->name('edit');
        Route::put('/{role}', [App\Http\Controllers\RoleController::class, 'update'])->name('update');
        Route::delete('/{role}', [App\Http\Controllers\RoleController::class, 'destroy'])->name('destroy');
    });
    
    // Settings Routes (Admin only)
    Route::middleware(['role:admin'])->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [App\Http\Controllers\SettingsController::class, 'index'])->name('index');
        Route::put('/', [App\Http\Controllers\SettingsController::class, 'update'])->name('update');
        Route::get('/backup', [App\Http\Controllers\SettingsController::class, 'backup'])->name('backup');
        Route::post('/backup/create', [App\Http\Controllers\SettingsController::class, 'createBackup'])->name('backup.create');
        Route::get('/logs', [App\Http\Controllers\SettingsController::class, 'logs'])->name('logs');
    });
});
