<?php

namespace Database\Seeders;

use App\Models\Employee\Employee;
use App\Models\Employee\Plan;
use App\Models\ListItemStyle;
use App\Models\PlaylistStyle;
use App\Models\Ratio;
use App\Models\User;
use App\Models\UserPlan;
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


        Employee::create([
            'email' => 'kosaysolh0@gmail.com',
            'password' => Hash::make('123456'),
        ]);


        PlaylistStyle::create([
            'type'=>'Normal',
            'description' => 'test',
        ]);
        PlaylistStyle::create([
            'type'=>'Interactive1',
            'description' => 'test',
        ]);
        PlaylistStyle::create([
            'type'=>'Interactive2',
            'description' => 'test',
        ]);


        ListItemStyle::create([
            'type'=>'normal',
        ]);
        ListItemStyle::create([
            'type'=>'2*2 r',
        ]);
        ListItemStyle::create([
            'type'=>'2*2 c',
        ]);
        ListItemStyle::create([
            'type'=>'3*3 r',
        ]);
        ListItemStyle::create([
            'type'=>'3*3 c',
        ]);
        ListItemStyle::create([
            'type'=>'4*4 r',
        ]);
        ListItemStyle::create([
            'type'=>'4*4 c',
        ]);


        User::create([
            'name'=> 'Tala',
            'email'=> 't@gmail.com',
            'password' => Hash::make('123456'),
            'country'=>'Lebanone'
        ]);
      
      
      User::create([
            'name'=> 'User A',
            'email'=> 'user@gmail.com',
            'password' => Hash::make('123456'),
            'country'=>'Lebanone'
        ]);

        Ratio::create([
            'ratio' => '16:9',
            'numerator' => '16',
            'denominator' => '9',
            'user_id' => 1
        ]);

        Plan::create([
            'name'=>'P1',
            'screen_number' => 10,
            'storage'=>1000,
            'price' =>1000,
            'offer' => 0,
            'plan_time' =>5,
            'is_recommended'=>0,
            'access_num' => 2
        ]);

        UserPlan::create([
            'user_id'=>1,
            'plan_id'=>1,
            'purchased_at' =>1000,
            'extra_space' => 0,
            'extra_screens' =>0,
            'num_screen' =>10,
            'used_screen'=>0,
            'storage'=>1000,
            'used_storage'=>0,
            'payment_date'=>'2025-09-01',
            'expire_date'=>'2025-09-30',
            'payment_type'=>'visa',
        ]);
      
      
      
      UserPlan::create([
            'user_id'=>2,
            'plan_id'=>1,
            'purchased_at' =>1000,
            'extra_space' => 0,
            'extra_screens' =>0,
            'num_screen' =>10,
            'used_screen'=>0,
            'storage'=>1000,
            'used_storage'=>0,
            'payment_date'=>'2025-09-01',
            'expire_date'=>'2025-09-30',
            'payment_type'=>'visa',
        ]);




    //       $this->call([
    //     UserPlanSeeder::class,
    // ]);
        // User::factory()->count(50)->create([
        //     'password' => Hash::make('password'), // Set a known password for all
        //     'is_verified' => true, // or false, depending on your app logic
        // ]);
    }
}
