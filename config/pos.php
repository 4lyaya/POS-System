<?php

return [
    'store' => [
        'name' => env('STORE_NAME', 'POS System'),
        'address' => env('STORE_ADDRESS', ''),
        'phone' => env('STORE_PHONE', ''),
        'email' => env('STORE_EMAIL', ''),
    ],

    'receipt' => [
        'header' => env('RECEIPT_HEADER', 'Terima kasih atas kunjungan Anda'),
        'footer' => env('RECEIPT_FOOTER', 'Barang yang sudah dibeli tidak dapat ditukar atau dikembalikan'),
        'print_copy' => env('RECEIPT_PRINT_COPY', 1),
    ],

    'tax' => [
        'rate' => env('TAX_RATE', 11), // in percentage
        'enabled' => env('TAX_ENABLED', true),
    ],

    'service_charge' => [
        'rate' => env('SERVICE_CHARGE_RATE', 0),
        'enabled' => env('SERVICE_CHARGE_ENABLED', false),
    ],

    'inventory' => [
        'low_stock_threshold' => env('LOW_STOCK_THRESHOLD', 10),
        'auto_generate_barcode' => env('AUTO_GENERATE_BARCODE', true),
        'default_unit' => env('DEFAULT_UNIT', 'pcs'),
    ],

    'pos' => [
        'default_payment_method' => env('DEFAULT_PAYMENT_METHOD', 'cash'),
        'enable_discount' => env('ENABLE_DISCOUNT', true),
        'enable_tax' => env('ENABLE_TAX', true),
        'enable_customer_selection' => env('ENABLE_CUSTOMER_SELECTION', true),
    ],

    'currency' => [
        'code' => env('CURRENCY_CODE', 'IDR'),
        'symbol' => env('CURRENCY_SYMBOL', 'Rp'),
        'position' => env('CURRENCY_POSITION', 'before'), // before or after
        'decimal' => env('CURRENCY_DECIMAL', 0),
        'separator' => env('CURRENCY_SEPARATOR', '.'),
    ],

    'backup' => [
        'enabled' => env('BACKUP_ENABLED', true),
        'retention_days' => env('BACKUP_RETENTION_DAYS', 30),
        'path' => env('BACKUP_PATH', 'backups'),
    ],
];
