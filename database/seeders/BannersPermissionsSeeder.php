<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class BannersPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        Banner::firstOrCreate([
            'ar_title' => 'العنوان الأول باللغة العربية',
            'en_title' => 'Title 1 in English',
            'url' => 'https://www.google.com',
            'status' => true,
        ]);

        Banner::firstOrCreate([
            'ar_title' => 'العنوان الثاني باللغة العربية',
            'en_title' => 'Title 2 in English',
            'url' => 'https://www.google.com',
            'status' => true,
        ]);

        Banner::firstOrCreate([
            'ar_title' => 'العنوان الثالث باللغة العربية',
            'en_title' => 'Title 3 in English',
            'url' => 'https://www.google.com',
            'status' => true,
        ]);
        Permission::firstOrCreate([
            'name' => 'show-banners',
            'display_name' => 'عرض البانرات',
        ]);
        Permission::firstOrCreate([
            'name' => 'create-banners',
            'display_name' => 'إنشاء البانرات',
        ]);
        Permission::firstOrCreate([
            'name' => 'edit-banners',
            'display_name' => 'تعديل البانرات',
        ]);
        Permission::firstOrCreate([
            'name' => 'delete-banners',
            'display_name' => 'حذف البانرات',
        ]);
    }
}
