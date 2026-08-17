<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create the Roles
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'manager']);
        Role::firstOrCreate(['name' => 'usher']);

        // 2. Create Admin with the 'role' column value
        $admin = User::firstOrCreate(
            ['email' => (string) env('ADMIN_EMAIL', 'admin@attendance.com')],
            [
                'name' => 'King Asah (Super Admin)',
                'password' => bcrypt((string) env('ADMIN_PASSWORD', 'password')),
                'role' => 'admin', // Add this line to satisfy the database constraint
            ]
        );

        $admin->assignRole('admin');

        // 3. Update the Test User too
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'usher',
        ]);
    }
}
