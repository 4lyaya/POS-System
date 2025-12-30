<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General Settings
            [
                'key' => 'store_name',
                'value' => 'Toko POS System',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Nama toko/tempat usaha'
            ],
            [
                'key' => 'store_address',
                'value' => 'Jl. Contoh No. 123, Kota Contoh',
                'type' => 'text',
                'group' => 'general',
                'description' => 'Alamat toko'
            ],
            [
                'key' => 'store_phone',
                'value' => '(021) 12345678',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Nomor telepon toko'
            ],
            [
                'key' => 'store_email',
                'value' => 'info@tokopos.com',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Email toko'
            ],

            // Financial Settings
            [
                'key' => 'currency',
                'value' => 'IDR',
                'type' => 'string',
                'group' => 'financial',
                'description' => 'Mata uang default'
            ],
            [
                'key' => 'tax_rate',
                'value' => '11',
                'type' => 'decimal',
                'group' => 'financial',
                'description' => 'Tarif pajak (dalam persen)'
            ],
            [
                'key' => 'service_charge_rate',
                'value' => '0',
                'type' => 'decimal',
                'group' => 'financial',
                'description' => 'Tarif biaya layanan (dalam persen)'
            ],

            // Receipt Settings
            [
                'key' => 'receipt_header',
                'value' => 'Terima kasih atas kunjungan Anda',
                'type' => 'text',
                'group' => 'receipt',
                'description' => 'Header struk'
            ],
            [
                'key' => 'receipt_footer',
                'value' => 'Barang yang sudah dibeli tidak dapat ditukar atau dikembalikan',
                'type' => 'text',
                'group' => 'receipt',
                'description' => 'Footer struk'
            ],
            [
                'key' => 'receipt_print_copy',
                'value' => '1',
                'type' => 'integer',
                'group' => 'receipt',
                'description' => 'Jumlah salinan struk yang dicetak'
            ],

            // Inventory Settings
            [
                'key' => 'low_stock_threshold',
                'value' => '10',
                'type' => 'integer',
                'group' => 'inventory',
                'description' => 'Batas minimum stok untuk notifikasi'
            ],
            [
                'key' => 'auto_generate_barcode',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'inventory',
                'description' => 'Generate barcode otomatis'
            ],

            // POS Settings
            [
                'key' => 'default_payment_method',
                'value' => 'cash',
                'type' => 'string',
                'group' => 'pos',
                'description' => 'Metode pembayaran default'
            ],
            [
                'key' => 'enable_discount',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'pos',
                'description' => 'Aktifkan fitur diskon'
            ],
            [
                'key' => 'enable_tax',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'pos',
                'description' => 'Aktifkan perhitungan pajak'
            ],

            // UI Settings
            [
                'key' => 'theme_color',
                'value' => 'blue',
                'type' => 'string',
                'group' => 'ui',
                'description' => 'Warna tema aplikasi'
            ],
            [
                'key' => 'dashboard_refresh_interval',
                'value' => '60',
                'type' => 'integer',
                'group' => 'ui',
                'description' => 'Interval refresh dashboard (detik)'
            ],
        ];

        foreach ($settings as $setting) {
            Setting::create($setting);
        }
    }
}
