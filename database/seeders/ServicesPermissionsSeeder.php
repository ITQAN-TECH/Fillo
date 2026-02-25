<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServicesPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        Service::firstOrCreate([
            'ar_name' => 'الخدمة الأولى باللغة العربية',
            'en_name' => 'Service 1 in English',
            'ar_description' => 'الوصف الأول باللغة العربية',
            'en_description' => 'Description 1 in English',
            'service_provider_price' => 100,
            'sale_price' => 120,
            'profit_amount' => 20,
            'duration_time_minutes' => 30,
            'category_id' => 1,
            'sub_category_id' => 1,
            'service_provider_id' => 1,
            'status' => true,
        ]);
        Permission::firstOrCreate([
            'name' => 'show-services',
            'display_name' => 'عرض الخدمات',
        ]);
        Permission::firstOrCreate([
            'name' => 'create-services',
            'display_name' => 'إنشاء الخدمات',
        ]);
        Permission::firstOrCreate([
            'name' => 'edit-services',
            'display_name' => 'تعديل الخدمات',
        ]);
        Permission::firstOrCreate([
            'name' => 'delete-services',
            'display_name' => 'حذف الخدمات',
        ]);
    }
}
