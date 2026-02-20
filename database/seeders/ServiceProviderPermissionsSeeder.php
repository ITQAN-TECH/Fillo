<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\ServiceProvider;
use App\Models\ServiceProviderCity;
use Illuminate\Database\Seeder;

class ServiceProviderPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ServiceProvider::create([
            'country_id' => 1,
            'city_id' => 1,
            'name' => 'مقدم خدمة',
            'store_name' => 'مقدم خدمة',
            'type' => 'individual',
            'phone' => '966599999999',
            'email' => 'serviceprovider@gmail.com',
            'full_address' => 'الرياض, المملكة العربية السعودية',
            'specialization' => 'مقدم خدمة',
            'working_hours_start' => '09:00:00',
            'working_hours_end' => '18:00:00',
            'daily_orders_count' => 5,
            'status' => true,
        ]);

        ServiceProviderCity::create([
            'service_provider_id' => 1,
            'city_id' => 1,
        ]);
        ServiceProviderCity::create([
            'service_provider_id' => 1,
            'city_id' => 2,
        ]);

        Permission::firstOrCreate([
            'name' => 'show-service-providers',
            'display_name' => 'عرض مقدمين الخدمات',
        ]);
        Permission::firstOrCreate([
            'name' => 'create-service-providers',
            'display_name' => 'إنشاء مقدم خدمة',
        ]);
        Permission::firstOrCreate([
            'name' => 'edit-service-providers',
            'display_name' => 'تعديل مقدم خدمة ',
        ]);
        Permission::firstOrCreate([
            'name' => 'delete-service-providers',
            'display_name' => 'حذف مقدم خدمة',
        ]);
    }
}
