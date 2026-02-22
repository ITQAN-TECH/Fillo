<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class BookingPermissionsService extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::firstOrCreate([
            'name' => 'show-bookings',
            'display_name' => 'عرض الحجوزات',
        ]);
        Permission::firstOrCreate([
            'name' => 'edit-bookings',
            'display_name' => 'تعديل الحجوزات',
        ]);
        Permission::firstOrCreate([
            'name' => 'delete-bookings',
            'display_name' => 'حذف الحجوزات',
        ]);
    }
}
