<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [4, 7]; // Plan IDs
        $userId = 1;
        $expireDate = Carbon::create(2030, 7, 25);

        foreach ($plans as $planId) {
            for ($i = 0; $i < 10; $i++) {
                // Random date in June 2025
                $day = rand(21, 22);
                $hour = rand(0, 23);
                $minute = rand(0, 59);
                $second = rand(0, 59);

                $createdAt = Carbon::create(2025, 7, $day, $hour, $minute, $second);
                $updatedAt = (clone $createdAt)->addHours(rand(1, 12));

                DB::table('user_plan')->insert([
                    'user_id'       => $userId,
                    'plan_id'       => $planId,
                    'purchased_at'  => 6000,
                    'extra_screens' => 0,
                    'extra_space'   => 0,
                    'num_screen'    => 20,
                    'storage'       => 5,
                    'used_storage'  => 2,
                    'expire_date'   => $expireDate,
                    'created_at'    => $createdAt,
                    'updated_at'    => $updatedAt,
                ]);

                $userId++;
            }
        }
    }
}
