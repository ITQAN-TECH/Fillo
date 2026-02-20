<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class CityPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        City::firstOrCreate([
            'country_id' => 1,
            'ar_name' => 'الرياض',
            'en_name' => 'Riyadh',
            'status' => true,
        ]);
        City::firstOrCreate([
            'country_id' => 1,
            'ar_name' => 'جدة',
            'en_name' => 'Jeddah',
            'status' => true,
        ]);
        City::firstOrCreate([
            'country_id' => 1,
            'ar_name' => 'الدمام',
            'en_name' => 'Dammam',
            'status' => true,
        ]);
        City::firstOrCreate([
            'country_id' => 2,
            'ar_name' => 'أبو ظبي',
            'en_name' => 'Abu Dhabi',
            'status' => true,
        ]);
        City::firstOrCreate([
            'country_id' => 2,
            'ar_name' => 'دبي',
            'en_name' => 'Dubai',
            'status' => true,
        ]);
        Permission::firstOrCreate([
            'name' => 'show-cities',
            'display_name' => 'عرض المدن',
        ]);
        Permission::firstOrCreate([
            'name' => 'create-cities',
            'display_name' => 'إنشاء المدن',
        ]);
        Permission::firstOrCreate([
            'name' => 'edit-cities',
            'display_name' => 'تعديل المدن',
        ]);
        Permission::firstOrCreate([
            'name' => 'delete-cities',
            'display_name' => 'حذف المدن',
        ]);
    }
}
