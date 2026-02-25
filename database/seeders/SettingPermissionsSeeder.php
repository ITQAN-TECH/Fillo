<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::firstOrCreate([
            'phone_number' => '+966500000000',
            'email' => 'info@example.com',
            'shipping_fee' => 20,
        ]);
        Permission::firstOrCreate([
            'name' => 'show-settings',
            'display_name' => 'عرض الإعدادات',
        ]);
        Permission::firstOrCreate([
            'name' => 'edit-settings',
            'display_name' => 'تعديل الإعدادات',
        ]);
    }
}
