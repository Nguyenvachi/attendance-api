<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Tạo Admin
        User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('123456'),
            'phone' => '0123456789',
            'role' => 'admin',
        ]);

        // Tạo Manager
        User::create([
            'name' => 'Manager',
            'email' => 'manager@test.com',
            'password' => Hash::make('123456'),
            'phone' => '0987654321',
            'role' => 'manager',
        ]);

        // Tạo Staff
        User::create([
            'name' => 'Staff',
            'email' => 'staff@test.com',
            'password' => Hash::make('123456'),
            'phone' => '0111222333',
            'role' => 'staff',
        ]);
    }
}
