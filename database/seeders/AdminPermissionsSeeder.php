<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class AdminPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::firstOrCreate([
            'name' => 'show-admins',
            'display_name' => 'عرض ادمن',
        ]);
        Permission::firstOrCreate([
            'name' => 'create-admins',
            'display_name' => 'إنشاء ادمن',
        ]);
        Permission::firstOrCreate([
            'name' => 'edit-admins',
            'display_name' => 'تعديل ادمن',
        ]);
        Permission::firstOrCreate([
            'name' => 'delete-admins',
            'display_name' => 'حذف ادمن',
        ]);
    }
}
