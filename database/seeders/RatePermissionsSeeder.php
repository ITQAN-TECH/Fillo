<?php

namespace Database\Seeders;

use App\Models\Rate;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RatePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        Rate::firstOrCreate([
            'rateable_id' => 1,
            'rateable_type' => 'App\Models\Service',
            'rate' => 5,
            'customer_id' => 1,
            'comment' => 'This is a test comment',
        ]);
        Permission::firstOrCreate([
            'name' => 'delete-rates',
            'display_name' => 'حذف التقييمات',
        ]);
    }
}
