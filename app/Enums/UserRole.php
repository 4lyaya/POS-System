<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case OPERATOR = 'operator';
    case KARYAWAN = 'karyawan';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::OPERATOR => 'Operator',
            self::KARYAWAN => 'Karyawan',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ADMIN => 'Super admin dengan akses penuh',
            self::OPERATOR => 'Manager toko dengan akses lengkap',
            self::KARYAWAN => 'Kasir dengan akses penjualan',
        };
    }

    public function permissions(): array
    {
        return match ($this) {
            self::ADMIN => [
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
                'manage-settings',
            ],
            self::OPERATOR => [
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
                'export-data',
            ],
            self::KARYAWAN => [
                'view-dashboard',
                'view-products',
                'manage-sales',
                'view-reports',
            ],
        };
    }

    public function defaultPermissions(): array
    {
        return $this->permissions();
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $slug) {
                return $case;
            }
        }
        return null;
    }
}
