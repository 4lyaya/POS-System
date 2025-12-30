<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            // UnitSeeder::class,
            // CategorySeeder::class,
            // SupplierSeeder::class,
            // CustomerSeeder::class,
            // ProductSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
