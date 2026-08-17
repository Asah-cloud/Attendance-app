<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run()
    {
        // 1. Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Create Permissions safely
        $permissions = [
            'view events',
            'mark attendance',
            'view reports',
            'edit events',
            'delete events',
            'import members',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]); // Changed 'create' to 'firstOrCreate'
        }

        // 3. Create Roles safely
        $usher = Role::firstOrCreate(['name' => 'usher']);
        $usher->syncPermissions(['view events', 'mark attendance', 'view reports']);

        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->syncPermissions(Permission::all());

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        // 4. Create User safely
        $adminUser = User::firstOrCreate(
            ['email' => (string) env('ADMIN_EMAIL', 'admin@attendance.com')],
            [
                'name' => 'System Admin',
                'password' => bcrypt((string) env('ADMIN_PASSWORD', 'password')),
                'role' => 'admin', // Keep this if your DB still requires it
            ]
        );

        // Assign role if they don't have it
        if (! $adminUser->hasRole('admin')) {
            $adminUser->assignRole($admin);
        }
    }
}
