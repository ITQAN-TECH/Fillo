<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class CountryPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        Country::firstOrCreate([
            'ar_name' => 'المملكة العربية السعودية',
            'en_name' => 'Saudi Arabia',
            'status' => true,
        ]);
        Country::firstOrCreate([
            'ar_name' => 'الامارات العربية المتحدة',
            'en_name' => 'United Arab Emirates',
            'status' => true,
        ]);
        Permission::firstOrCreate([
            'name' => 'show-countries',
            'display_name' => 'عرض دول',
        ]);
        Permission::firstOrCreate([
            'name' => 'create-countries',
            'display_name' => 'إنشاء دولة',
        ]);
        Permission::firstOrCreate([
            'name' => 'edit-countries',
            'display_name' => 'تعديل دولة',
        ]);
        Permission::firstOrCreate([
            'name' => 'delete-countries',
            'display_name' => 'حذف دولة',
        ]);
    }
}
