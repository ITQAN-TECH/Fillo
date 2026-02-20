<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class CustomerPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Customer::firstOrCreate([
            'name' => 'ضيف',
            'email' => 'mahmoud.mohammad.19982000@gmail.com',
            'phone' => '966987654321',
            'password' => '12345678',
        ]);
        Permission::firstOrCreate([
            'name' => 'show-customers',
            'display_name' => 'عرض العملاء',
        ]);
        Permission::firstOrCreate([
            'name' => 'create-customers',
            'display_name' => 'إنشاء العملاء',
        ]);
        Permission::firstOrCreate([
            'name' => 'edit-customers',
            'display_name' => 'تعديل العملاء',
        ]);
        Permission::firstOrCreate([
            'name' => 'delete-customers',
            'display_name' => 'حذف العملاء',
        ]);
    }
}
