<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class OrderCancellationRequestPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'show-order-cancellation-requests',
                'display_name' => 'عرض طلبات إلغاء الطلبات',
            ],
            [
                'name' => 'edit-order-cancellation-requests',
                'display_name' => 'إدارة طلبات إلغاء الطلبات',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }
}
