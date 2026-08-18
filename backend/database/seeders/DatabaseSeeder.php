<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => 'password']
        );
        $admin->assignRole('Admin');

        $ts = User::firstOrCreate(
            ['email' => 'ts@example.com'],
            ['name' => 'Technical Support', 'password' => 'password']
        );
        $ts->assignRole('TS');

        $agent = User::firstOrCreate(
            ['email' => 'agent@example.com'],
            ['name' => 'Agent', 'password' => 'password']
        );
        $agent->assignRole('Agent');
    }
}
