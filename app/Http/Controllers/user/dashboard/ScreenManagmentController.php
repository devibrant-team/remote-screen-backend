<?php

namespace App\Http\Controllers\User\dashboard;

use App\Events\ScreenLinked;
use App\Http\Controllers\Controller;
use App\Models\Branches;
use App\Models\Ratio;
use App\Models\Screens;
use App\Models\UserScreens;
use App\Models\UserPlan;
use App\Models\Schedule;
use App\Models\ScheduleDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ScreenManagmentController extends Controller
{


    public function getRatio(Request $request){

         $user = auth()->user();
    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $ratio = Ratio::where('user_id', $user->id)->get();

        $formattedData = $ratio->map(function ($playlist) {
        return [
            'id' => $playlist->id,
            'ratio' => $playlist->ratio,
            'numerator ' => $playlist->numerator,
            'denominator' => $playlist->denominator,
            'width' => $playlist->width,
            'height' => $playlist->height,
        ];
    });


   return response()->json([
        'success' => true,
        'ratio' => $formattedData,
    ]);

    }

    public function postBranch(Request $request){
    
        $request->validate([
            'name'=>'required',
        ]);     
        
    //     $user = auth()->user();
    // if (!$user || !$request->user()->tokenCan('user_dashboard')) {
    //     return response()->json(['error' => 'Unauthorized'], 401);
    // }
    $branch = Branches::create([
        'name' => $request->name,
        'user_id' => 1,
    ]);
   
    return response()->json([
        'success' => true,
        'branch' => $branch,
    ]);

    }

    public function getBranch (Request $request){

        $user = auth()->user();

    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    
    $getuserBranch= Branches::where('user_id',$user->id)->get();

    return response()->json([
        'success' => true,
        'branch' => $getuserBranch,
    ]);

    }


public function createScreen(Request $request)
{
  
    do {
        // Generate a random 6-digit number
        $code = mt_rand(100000, 999999);
    } while (Screens::where('code', $code)->exists()); // Ensure uniqueness in DB
	
    $screen = Screens::create([
        'name' => 'newRegistered',
        'code' => $code,
        'is_active' => 0,
        'platform'=>'android',
    ]);

    return response()->json($screen);
}

public function addScreen(Request $request)
    {
        $request->validate([
            'code'      => 'required|integer|digits:6',
            'name'      => 'required|string',
            'ratio_id'  => 'required|exists:ratio,id',
            'branch_id' => 'required|exists:branches,id',
            'group_id'  => 'nullable|exists:groups,id',
        ]);

        $user = $request->user();
        if (!$user || !$user->tokenCan('user_dashboard')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 1) Find the screen by code (unlinked/unused)
        $screen = Screens::where('code', $request->code)->first();
        if (!$screen) {
            return response()->json(['success' => false, 'message' => 'The code is incorrect'], 422);
        }

        // 2) Block linking the same screen twice for this user
        $alreadyLinked = UserScreens::where('user_id', $user->id)
            ->where('screen_id', $screen->id)
            ->exists();

        if ($alreadyLinked) {
            return response()->json(['success' => false, 'message' => 'This Screen is already linked to your account'], 409);
        }

        // We'll broadcast AFTER a successful commit
        $originalCode = (string) $request->code;
        $nextUrl      = url("/screen/{$screen->id}/ready");

        try {
            DB::beginTransaction();

            // 3) Lock the user's plan row and enforce the limit atomically
            // Option A: conditional atomic increment (single UPDATE)
            $updated = UserPlan::where('user_id', $user->id)
                ->whereColumn('used_screen', '<', 'num_screen')
                ->increment('used_screen');

            if ($updated === 0) {
                DB::rollBack();
                return response()->json(['error' => 'You have reached your max screen number.'], 403);
            }

            // 4) Lock the screen row to prevent concurrent consumption of the same code
            $lockedScreen = Screens::whereKey($screen->id)->lockForUpdate()->first();

            // If the code was consumed concurrently, fail gracefully
            if ($lockedScreen->code !== (int) $request->code) {
                DB::rollBack();
                return response()->json(['error' => 'This code was just used. Try another one.'], 409);
            }

            // 5) Update screen details (set code to null to mark it consumed)
            // If `code` column is NOT NULL in your DB, remove the 'code' => null line or make the column nullable in a migration.
            $lockedScreen->update([
                'name' => $request->name,
                'code' => null, // comment this out if the column is NOT NULL
            ]);

            // 6) Create the user-screen link
            $userScreen = UserScreens::create([
                'screen_id' => $lockedScreen->id,
                'user_id'   => $user->id,
                'ratio_id'  => $request->ratio_id,
                'branch_id' => $request->branch_id,
                'group_id'  => $request->group_id,
                'is_extra'  => 0,
            ]);

            DB::commit();

            // 7) Broadcast AFTER commit so others see consistent state
            try {
                broadcast(new ScreenLinked(
                    originalCode: $originalCode,
                    screenId: $lockedScreen->id,
                    nextUrl: $nextUrl
                ))->toOthers();
            } catch (Throwable $e) {
                Log::warning('Broadcast ScreenLinked failed after commit', [
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => true,
                    'data'    => $userScreen,
                    'warning' => 'Linked, but realtime broadcast failed. Check logs.',
                ], 200);
            }

            return response()->json(['success' => true, 'data' => $userScreen], 200);

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('addScreen failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Server error. Please try again.'], 500);
        }
    }

  
public function getusersinglescreens(Request $request)
{
    $user = auth()->user();
    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    // How many future days to include? (default 14, clamp 1..60)
    $days = (int) $request->query('days', 14);
    $days = max(1, min($days, 60));

    // Time window [today .. today+days]
    $tz        = config('app.timezone', 'UTC'); // your app likely 'Asia/Beirut'
    $startDate = Carbon::now($tz)->startOfDay();
    $endDate   = (clone $startDate)->addDays($days);

    // 1) Get user-linked screens
    $screens = UserScreens::where('user_id', $user->id)
        ->with([
            'screen:id,name,is_active,platform,last_seen_at',
            'ratio:id,ratio',
            'branch:id,name',
        ])
        ->get();

    if ($screens->isEmpty()) {
        return response()->json(['error' => 'No Linked Screens yet'], 404);
    }

    // Collect the underlying screen IDs
    $screenIds = $screens->pluck('screen_id')->filter()->values();

    // 2) Pull upcoming schedules for these screens in one go
    // Overlap condition: schedule.start_day <= endDate AND schedule.end_day >= startDate
    $schedules = Schedule::query()
        ->where('user_id', $user->id)
        ->whereDate('start_day', '<=', $endDate->toDateString())
        ->whereDate('end_day', '>=', $startDate->toDateString())
        ->whereHas('details', function ($q) use ($screenIds) {
            $q->whereIn('screen_id', $screenIds);
        })
        ->with([
            'details' => function ($q) use ($screenIds) {
                $q->whereIn('screen_id', $screenIds)
                  ->select('id', 'schedule_id', 'screen_id');
            },
            // if you want playlist name in the payload, keep this and map it below
            'playlist:id,name',
        ])
        ->get([
            'id', 'user_id', 'playlist_id', 'group_id',
            'start_time', 'end_time', 'start_day', 'end_day',
        ]);

    // 3) Bucket schedules by screen_id
    $byScreen = [];
    foreach ($schedules as $sch) {
        foreach ($sch->details as $det) {
            $byScreen[$det->screen_id][] = [
                'scheduleId' => $sch->id,
                'playlistId' => $sch->playlist_id,
                'playlistName' => optional($sch->playlist)->name,
                'groupId'   => $sch->group_id,
                'startDay'  => $sch->start_day,   // DATE (YYYY-MM-DD)
                'endDay'    => $sch->end_day,     // DATE (YYYY-MM-DD)
                'startTime' => $sch->start_time,  // HH:MM:SS
                'endTime'   => $sch->end_time,    // HH:MM:SS
            ];
        }
    }

    // 4) Format response
    $formatted = $screens->map(function ($row) use ($byScreen) {
        $screenId = $row->screen_id;

        return [
            'id'         => $row->id,
            'screenId'   => optional($row->screen)->id,
            'screenName' => optional($row->screen)->name,
            'ratio'      => optional($row->ratio)->ratio,
            'ratioId'      => optional($row->ratio)->id,
            'branchName' => optional($row->branch)->name,
            'active'     => optional($row->screen)->is_active,
            'lastSeen'   => optional($row->screen)->last_seen_at,
            // attach upcoming schedules for this screen
            'schedules'  => array_values($byScreen[$screenId] ?? []),
        ];
    });

    return response()->json([
        'success' => true,
        'screens' => $formatted,
        'window'  => [
            'from' => $startDate->toDateString(),
            'to'   => $endDate->toDateString(),
        ],
    ]);
}
  
  
    
}
