<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPlan;
use Illuminate\Http\Request;
use Carbon\Carbon;

class UserDataController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if (!$user || !$user->tokenCan('Admin')) {
            return response()->json(['error' => 'Unauthorized1234'], 401);
        }

        $userCount = User::count();
        // count of screen for all user
        $screencount = \App\Models\Screens::count();
        $totalincome = \App\Models\UserPlan::sum('purchased_at');
        $usedStorage = \App\Models\UserPlan::sum('used_storage');
        $allStorage = \App\Models\UserPlan::sum('storage');
        // $totalStorage = $allStorage - $usedStorage;

        return response()->json([
            'success' => true,
            'Total_users' => $userCount,
            'Total_Screens' => $screencount,
            'Total_Income' => $totalincome,
            'Total_storage' => $allStorage,

        ]);
    }
    // function to get how in purchased_at in day and month and year




public function getPlanOverview(Request $request)
{
      $user = auth()->user();

        if (!$user || !$user->tokenCan('Admin')) {
            return response()->json(['error' => 'Unauthorized1234'], 401);
        }
    // Get extra prices
    $extraScreenPrice = \App\Models\Employee\Custom::where('type', 'Screen')->value('price');
    $extraStoragePrice = \App\Models\Employee\Custom::where('type', 'Storage')->value('price');

    if (!$extraScreenPrice || !$extraStoragePrice) {
        return response()->json(['error' => 'Custom prices not found'], 404);
    }

    // Validate input
    $request->validate([
        'year'  => 'nullable|integer',
        'month' => 'nullable|integer|min:1|max:12',
        'day'   => 'nullable|integer|min:1|max:31',
    ]);
    // return $request;
    $now = Carbon::now();

    // Handle time filters
    if ($request->day) {
        // Day filter
        $year = $request->year ? (int)$request->year : $now->year;
        $month = $request->month ? (int)$request->month : $now->month;
        $day = (int)$request->day;

        $date = Carbon::create($year, $month, $day)->startOfDay();
        $previousDate = $date->copy()->subMonth();

        $currentQuery = UserPlan::whereBetween('created_at', [
            $date->copy()->startOfDay(),
            $date->copy()->endOfDay()
        ]);

        $previousCount = UserPlan::whereBetween('created_at', [
            $previousDate->copy()->startOfDay(),
            $previousDate->copy()->endOfDay()
        ])->count();
    }
    elseif ($request->month) {
        // Month filter
        $year = $request->year ? (int)$request->year : $now->year;
        $month = (int)$request->month;

        $currentQuery = UserPlan::whereYear('created_at', $year)
                                ->whereMonth('created_at', $month);

        $previousCount = UserPlan::whereYear('created_at', $year - 1)
                                ->whereMonth('created_at', $month)
                                ->count();
    }
    elseif ($request->year) {
        // Year filter
        $year = (int)$request->year;

        $currentQuery = UserPlan::whereYear('created_at', $year);
        $previousCount = UserPlan::whereYear('created_at', $year - 1)->count();
    }
    else {
        // Default to today
        $date = $now->copy()->startOfDay();
        $previousDate = $date->copy()->subMonth();

        $currentQuery = UserPlan::whereBetween('created_at', [
            $date->copy()->startOfDay(),
            $date->copy()->endOfDay()
        ]);

        $previousCount = UserPlan::whereBetween('created_at', [
            $previousDate->copy()->startOfDay(),
            $previousDate->copy()->endOfDay()
        ])->count();
    }

    // Get current count
    $currentCount = $currentQuery->count();

    // 1. Base income from purchased_at
    $baseIncome = (clone $currentQuery)->sum('purchased_at');

    // 2. Extra screens total
    $totalExtraScreens = (clone $currentQuery)->sum('extra_screens');
    $extraScreensTotal = $totalExtraScreens * $extraScreenPrice;

    // 3. Extra storage total
    $totalExtraStorage = (clone $currentQuery)->sum('extra_space');
    $extraStorageTotal = $totalExtraStorage * $extraStoragePrice;

    // 4. Total income
    $totalIncome = $baseIncome + $extraScreensTotal + $extraStorageTotal;

    // Calculate percent change
    $percentChange = $previousCount > 0
        ? round((($currentCount - $previousCount) / $previousCount) * 100, 2)
        : null;

    return response()->json([
        'success'             => true,
        'total_income'        => $totalIncome,
        'percent_change'  => $percentChange,
    ]);
}







public function getusersplan(Request $request)
{
    $request->validate([
        'plan_id' => 'required|integer|min:0',
        'join' => 'nullable|string'
    ]);

    $user = auth()->user();

    if (!$user || !$request->user()->tokenCan('Admin')) {
        return response()->json(['error' => 'Unauthorized1234'], 401);
    }

    $query = UserPlan::with('plan:id,name', 'user:id,name,email');

    $planId = (int)$request->plan_id;
    $join = $request->join;

    $year = null;
    $month = null;

    // 🔍 Detect year/month from join
    if ($join) {
        $parts = explode('-', $join);

        if (count($parts) === 2) {
            // join = 2025-07
            $year = (int)$parts[0];
            $month = (int)$parts[1];
        } elseif (strlen($join) === 4 && is_numeric($join)) {
            // join = 2025
            $year = (int)$join;
        } elseif (is_numeric($join) && (int)$join >= 1 && (int)$join <= 12) {
            // join = 07 or 7 (month only)
            $month = (int)$join;
            $year = (int)date('Y'); // use current year
        }
    }

    // Filter by plan_id (if not 0)
    if ($planId !== 0) {
        $query->where('plan_id', $planId);
    }

    // Apply date filters
    if ($year && $month) {
        $query->whereYear('created_at', $year)
              ->whereMonth('created_at', $month);
    } elseif ($year) {
        $query->whereYear('created_at', $year);
    } elseif ($month) {
        $query->whereYear('created_at', $year)->whereMonth('created_at', $month);
    }

    $userPlans = $query->paginate(10);

    $formatted = $userPlans->map(function ($user) {
        return [
            'id' => $user->user->id ?? null,
            'name' => $user->user->name ?? null,
            'email' => $user->user->email ?? null,
            'plan_name' => $user->plan->name ?? null,
            'used_storage' => $user->plan->used_storage ?? null,
            'joined' => optional($user->created_at)->format('Y-m-d'),
            'expire' => $user->expire_date ?? null,
        ];
    });

    return response()->json([
        'success' => true,
        'users' => $formatted,
        'current_page' => $userPlans->currentPage(),
        'last_page' => $userPlans->lastPage(),
        'total' => $userPlans->total(),
        'per_page' => $userPlans->perPage(),
    ]);
}






    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string'
        ]);

        $user = auth()->user();

        if (!$user || !$request->user()->tokenCan('Admin')) {
            return response()->json(['error' => 'Unauthorized1234'], 401);
        }

        $query = $request->input('query');

        $users = User::where(function ($q) use ($query) {
            $q->where('email', 'like', $query . '%')
                ->orWhere('name', 'like', $query . '%');
        })->get();

        $formattedUsers = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ];
        });

        return response()->json([
            'success' => true,
            'user' => $formattedUsers
        ]);
    }
}
