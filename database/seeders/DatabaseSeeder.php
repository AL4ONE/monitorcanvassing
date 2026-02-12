<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create supervisor (acts as admin)
        User::create([
            'name' => 'Admin',
            'email' => 'admin@local.test',
            'password' => 'password',
            'role' => 'supervisor',
        ]);

        // Create supervisor
        User::create([
            'name' => 'Supervisor',
            'email' => 'supervisor@stiqr.com',
            'password' => 'password',
            'role' => 'supervisor',
        ]);

        // Create sample staff
        User::create([
            'name' => 'Staff 1',
            'email' => 'staff1@stiqr.com',
            'password' => 'password',
            'role' => 'staff',
        ]);

        User::create([
            'name' => 'Staff 2',
            'email' => 'staff2@stiqr.com',
            'password' => 'password',
            'role' => 'staff',
        ]);
    }
}
