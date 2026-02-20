<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // For admin
        // \App\Models\User::factory(10)->create();

        $this->call(AdminPermissionsSeeder::class);
        $this->call(RolesPermissionsSeeder::class);
        $this->call(SettingPermissionsSeeder::class);
        $this->call(PagePermissionsSeeder::class);
        $this->call(FaqPermissionsSeeder::class);
        $this->call(CountryPermissionsSeeder::class);
        $this->call(CityPermissionsSeeder::class);
        $this->call(BannersPermissionsSeeder::class);
        $this->call(CustomerPermissionsSeeder::class);
        $this->call(SupportChatsPermissionSeeder::class);
        $this->call(NotificationsFromAdminPermissionsSeeder::class);
        $this->call(CategoryPermissionsSeeder::class);
        $this->call(ServiceProviderPermissionsSeeder::class);
        $this->call(ServicesPermissionsSeeder::class);
        $this->call(RatePermissionsSeeder::class);
        $this->call(CouponPermissionsSeeder::class);

        \App\Models\User::firstOrCreate([
            'name' => 'Admin',
            'email' => 'admin@admin.com',
            'phone' => '966987654321',
            'password' => '12345678',
            'remember_token' => Str::random(10),
        ]);

        \App\Models\Role::firstOrCreate([
            'name' => 'admin',
            'display_name' => 'ادمن',
            'description' => 'ادمن',
        ]);

        DB::table('role_user')->insert([
            'role_id' => '1',
            'user_id' => '1',
            'user_type' => 'App\Models\User',
        ]);

        $permissions = Permission::get();
        foreach ($permissions as $item) {
            DB::table('permission_role')->insert([
                'permission_id' => $item->id,
                'role_id' => '1',
            ]);
        }
    }
}
