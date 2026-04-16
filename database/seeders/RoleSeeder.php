<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleSeeder extends Seeder
{
    public function run()
    {
        // 1. Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

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
$marker = Role::firstOrCreate(['name' => 'marker']);
$marker->syncPermissions(['view events', 'mark attendance', 'view reports']);

$admin = Role::firstOrCreate(['name' => 'admin']);
$admin->syncPermissions(Permission::all());

// 4. Create User safely
$adminUser = User::firstOrCreate(
    ['email' => 'admin@attendance.com'], // Check by email
    [
        'name' => 'System Admin',
        'password' => bcrypt('password123'),
        'role' => 'admin', // Keep this if your DB still requires it
    ]
);

// Assign role if they don't have it
if (!$adminUser->hasRole('admin')) {
    $adminUser->assignRole($admin);
}
    }
}