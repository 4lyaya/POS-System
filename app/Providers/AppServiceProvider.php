<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\POS\SaleService;
use App\Services\POS\CartService;
use App\Services\POS\ReceiptService;
use App\Services\Inventory\PurchaseService;
use App\Services\Inventory\ProductService;
use App\Services\Inventory\StockService;
use App\Services\Report\SalesReportService;
use App\Services\Report\InventoryReportService;
use App\Services\Report\FinancialReportService;
use App\Services\ExportService;
use App\Services\PdfService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register Services
        $this->app->singleton(SaleService::class);
        $this->app->singleton(CartService::class);
        $this->app->singleton(ReceiptService::class);
        $this->app->singleton(PurchaseService::class);
        $this->app->singleton(ProductService::class);
        $this->app->singleton(StockService::class);
        $this->app->singleton(SalesReportService::class);
        $this->app->singleton(InventoryReportService::class);
        $this->app->singleton(FinancialReportService::class);
        $this->app->singleton(ExportService::class);
        $this->app->singleton(PdfService::class);

        // Register Repositories
        $this->app->bind(
            \App\Repositories\Interfaces\ProductRepositoryInterface::class,
            \App\Repositories\ProductRepository::class
        );

        $this->app->bind(
            \App\Repositories\Interfaces\SaleRepositoryInterface::class,
            \App\Repositories\SaleRepository::class
        );

        $this->app->bind(
            \App\Repositories\Interfaces\PurchaseRepositoryInterface::class,
            \App\Repositories\PurchaseRepository::class
        );

        $this->app->bind(
            \App\Repositories\Interfaces\UserRepositoryInterface::class,
            \App\Repositories\UserRepository::class
        );

        $this->app->bind(\App\Exports\TransactionReportExport::class, function ($app, $params) {
            return new \App\Exports\TransactionReportExport(
                $params['startDate'] ?? now()->startOfMonth(),
                $params['endDate'] ?? now()->endOfMonth(),
                $params['transactionType'] ?? 'both'
            );
        });

        $this->app->bind(\App\Imports\PurchasesImport::class, function ($app, $params) {
            return new \App\Imports\PurchasesImport(
                $params['supplierId'] ?? null,
                $params['purchaseDate'] ?? null,
                $params['skipErrors'] ?? false
            );
        });
    }

    public function boot(): void
    {
        // View composers or other boot operations
    }
}
