<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Super admin dengan akses penuh',
                'permissions' => [
                    'view-dashboard',
                    'manage-users',
                    'manage-roles',
                    'manage-products',
                    'manage-categories',
                    'manage-units',
                    'manage-suppliers',
                    'manage-customers',
                    'manage-purchases',
                    'manage-sales',
                    'manage-stock',
                    'view-reports',
                    'export-data',
                    'manage-settings'
                ],
                'is_default' => false
            ],
            [
                'name' => 'Operator',
                'slug' => 'operator',
                'description' => 'Manager toko dengan akses lengkap kecuali user management',
                'permissions' => [
                    'view-dashboard',
                    'manage-products',
                    'manage-categories',
                    'manage-units',
                    'manage-suppliers',
                    'manage-customers',
                    'manage-purchases',
                    'manage-sales',
                    'manage-stock',
                    'view-reports',
                    'export-data'
                ],
                'is_default' => false
            ],
            [
                'name' => 'Karyawan',
                'slug' => 'karyawan',
                'description' => 'Kasir dengan akses penjualan dan lihat stok',
                'permissions' => [
                    'view-dashboard',
                    'view-products',
                    'manage-sales',
                    'view-reports'
                ],
                'is_default' => true
            ]
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
