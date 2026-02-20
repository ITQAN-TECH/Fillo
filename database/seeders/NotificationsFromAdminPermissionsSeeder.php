<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class NotificationsFromAdminPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::firstOrCreate([
            'name' => 'show-notifications_from_admins',
            'display_name' => 'عرض الإشعارات من الأدمن',
        ]);
        Permission::firstOrCreate([
            'name' => 'create-notifications_from_admins',
            'display_name' => 'إنشاء الإشعارات من الأدمن',
        ]);
        Permission::firstOrCreate([
            'name' => 'delete-notifications_from_admins',
            'display_name' => 'حذف الإشعارات من الأدمن',
        ]);
    }
}
