<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class ProductPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        Permission::firstOrCreate([
            'name' => 'show-products',
            'display_name' => 'عرض المنتجات',
        ]);
        Permission::firstOrCreate([
            'name' => 'create-products',
            'display_name' => 'إنشاء المنتجات',
        ]);
        Permission::firstOrCreate([
            'name' => 'edit-products',
            'display_name' => 'تعديل المنتجات',
        ]);
        Permission::firstOrCreate([
            'name' => 'delete-products',
            'display_name' => 'حذف المنتجات',
        ]);
    }
}
