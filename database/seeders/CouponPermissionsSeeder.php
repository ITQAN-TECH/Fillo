<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class CouponPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        Coupon::firstOrCreate([
            'code' => 'PRODUCT_COUPON',
            'discount_percentage' => 10,
            'expiry_date' => '2027-02-20',
            'type' => 'product',
            'status' => true,
        ]);
        Coupon::firstOrCreate([
            'code' => 'SERVICE_COUPON',
            'discount_percentage' => 10,
            'expiry_date' => '2027-02-20',
            'type' => 'service',
            'status' => true,
        ]);
        Permission::firstOrCreate([
            'name' => 'show-coupons',
            'display_name' => 'عرض الكوبونات',
        ]);
        Permission::firstOrCreate([
            'name' => 'create-coupons',
            'display_name' => 'إنشاء الكوبونات',
        ]);
        Permission::firstOrCreate([
            'name' => 'edit-coupons',
            'display_name' => 'تعديل الكوبونات',
        ]);
        Permission::firstOrCreate([
            'name' => 'delete-coupons',
            'display_name' => 'حذف الكوبونات',
        ]);
    }
}
