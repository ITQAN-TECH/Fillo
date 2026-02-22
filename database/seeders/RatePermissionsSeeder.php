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
        Permission::firstOrCreate([
            'name' => 'delete-rates',
            'display_name' => 'حذف التقييمات',
        ]);
    }
}
