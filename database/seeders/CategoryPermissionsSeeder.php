<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Permission;
use App\Models\SubCategory;
use Illuminate\Database\Seeder;

class CategoryPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        Category::firstOrCreate([
            'ar_title' => 'العناية',
            'en_title' => 'Care',
            'type' => 'service',
            'status' => true,
        ]);
        SubCategory::firstOrCreate([
            'category_id' => 1,
            'ar_title' => 'حلاقة الشعر',
            'en_title' => 'Haircut',
            'status' => true,
        ]);
        SubCategory::firstOrCreate([
            'category_id' => 1,
            'ar_title' => 'تنظيف الخيل',
            'en_title' => 'Horse cleaning',
            'status' => true,
        ]);
        Category::firstOrCreate([
            'ar_title' => 'مستلزمات العناية',
            'en_title' => 'Care products',
            'type' => 'product',
            'status' => true,
        ]);
        SubCategory::firstOrCreate([
            'category_id' => 2,
            'ar_title' => 'مسحات الشعر',
            'en_title' => 'Hair brushes',
            'status' => true,
        ]);
        SubCategory::firstOrCreate([
            'category_id' => 2,
            'ar_title' => 'مسحات الجسم',
            'en_title' => 'Body brushes',
            'status' => true,
        ]);
        Permission::firstOrCreate([
            'name' => 'show-categories',
            'display_name' => 'عرض التصنيفات',
        ]);
        Permission::firstOrCreate([
            'name' => 'create-categories',
            'display_name' => 'إنشاء تصنيف',
        ]);
        Permission::firstOrCreate([
            'name' => 'edit-categories',
            'display_name' => 'تعديل تصنيف',
        ]);
        Permission::firstOrCreate([
            'name' => 'delete-categories',
            'display_name' => 'حذف تصنيف',
        ]);
    }
}
