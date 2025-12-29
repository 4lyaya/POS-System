<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();
        $operatorRole = Role::where('slug', 'operator')->first();
        $karyawanRole = Role::where('slug', 'karyawan')->first();

        $users = [
            [
                'name' => 'Administrator',
                'email' => 'admin@pos.com',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'phone' => '081234567890',
                'address' => 'Jl. Administrasi No. 1',
                'is_active' => true
            ],
            [
                'name' => 'Operator Toko',
                'email' => 'operator@pos.com',
                'password' => Hash::make('password'),
                'role_id' => $operatorRole->id,
                'phone' => '081234567891',
                'address' => 'Jl. Operator No. 2',
                'is_active' => true
            ],
            [
                'name' => 'Kasir 1',
                'email' => 'kasir1@pos.com',
                'password' => Hash::make('password'),
                'role_id' => $karyawanRole->id,
                'phone' => '081234567892',
                'address' => 'Jl. Kasir No. 3',
                'is_active' => true
            ],
            [
                'name' => 'Kasir 2',
                'email' => 'kasir2@pos.com',
                'password' => Hash::make('password'),
                'role_id' => $karyawanRole->id,
                'phone' => '081234567893',
                'address' => 'Jl. Kasir No. 4',
                'is_active' => true
            ]
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}
