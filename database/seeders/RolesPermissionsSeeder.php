<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class RolesPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::firstOrCreate([
            'name' => 'show-roles',
            'display_name' => 'عرض الأدوار',
        ]);
        Permission::firstOrCreate([
            'name' => 'create-roles',
            'display_name' => 'إنشاء دور',
        ]);
        Permission::firstOrCreate([
            'name' => 'edit-roles',
            'display_name' => 'تعديل دور',
        ]);
        Permission::firstOrCreate([
            'name' => 'delete-roles',
            'display_name' => 'حذف دور',
        ]);
    }
}
