<?php

namespace Database\Seeders;

use App\Models\Employee\Employee;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

       
        // Employee::create([
        //     'email' => 'kosaysolh0@gmail.com',
        //     'password' => Hash::make('123456'),
        // ]);

        User::factory()->count(50)->create([
            'password' => Hash::make('password'), // Set a known password for all
            'is_verified' => true, // or false, depending on your app logic
        ]);
    }
}
