<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class PagePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::firstOrCreate([
            'name' => 'show-pages',
            'display_name' => 'عرض الصفحات',
        ]);
        Permission::firstOrCreate([
            'name' => 'edit-pages',
            'display_name' => 'تعديل الصفحات',
        ]);

        Page::firstOrCreate([
            'ar_about_us' => 'من نحن باللغة العربية',
            'en_about_us' => 'About Us in English',
            'ar_terms_and_conditions' => 'الشروط والأحكام باللغة العربية',
            'en_terms_and_conditions' => 'Terms and Conditions in English',
            'ar_privacy_policy' => 'سياسة الخصوصية باللغة العربية',
            'en_privacy_policy' => 'Privacy Policy in English',
        ]);
    }
}
