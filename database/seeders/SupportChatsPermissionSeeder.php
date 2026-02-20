<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class SupportChatsPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::create([
            'name' => 'show-support_chats',
            'display_name' => 'عرض خدمة العملاء',
        ]);
        Permission::create([
            'name' => 'create-support_chats',
            'display_name' => 'ارسال رسالة في خدمة العملاء',
        ]);
    }
}
