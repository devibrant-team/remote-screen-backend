<?php

namespace App\Http\Controllers\User\dashboard;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Schedule;
use App\Models\ScheduleDetails;
use App\Models\UserScreens;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$request->user()->tokenCan('user_dashboard')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
	
        $data = $this->validateRequest($request);

        try {
            return DB::transaction(function () use ($user, $data) {
                $screenIds = $this->resolveScreenIds($user->id, $data);
                if ($screenIds->isEmpty()) {
                    return response()->json(['error' => 'No screens selected'], 422);
                }

                if ($notOwned = $this->findUnownedScreens($user->id, $screenIds)) {
                    return response()->json([
                        'error'      => 'Some screens do not belong to the authenticated user',
                        'screen_ids' => $notOwned,
                    ], 422);
                }

                if ($conflicts = $this->findConflicts($user->id, $data, $screenIds)) {
                    return response()->json([
                        'error'     => 'Schedule conflicts detected for one or more screens.',
                        'conflicts' => $conflicts,
                    ], 422);
                }

                $schedule = $this->createSchedule($user->id, $data, $screenIds);

                return response()->json([
                    'success'  => true,
                    'message'  => 'Schedule created',
                    'schedule' => $schedule,
                    'attached_screen_ids' => $screenIds,
                ], 201);
            });
        } catch (\Throwable $e) {
            Log::error('Schedule store failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create schedule',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * ✅ Validate the incoming request
     */
    private function validateRequest(Request $request): array
    {
     
        $data = $request->validate([
            'playlistId'        => 'required|integer|exists:playlist,id',
            'groupId'           => 'nullable|integer|exists:groups,id',
            'screens'            => 'required_without:group_id|array|min:1',
            'screens.*.screenId' => 'required|integer|distinct|exists:screens,id',

            'startDate' => 'required|date_format:d-m-Y',
            'endDate'   => 'required|date_format:d-m-Y|after_or_equal:startDate',
            'startTime' => 'required|date_format:H:i:s',
            'endTime'   => 'required|date_format:H:i:s',
        ]);

        // Convert d-m-Y → Y-m-d for DB
        $data['startDate'] = Carbon::createFromFormat('d-m-Y', $data['startDate'])->toDateString();
        $data['endDate']   = Carbon::createFromFormat('d-m-Y', $data['endDate'])->toDateString();

        if ($data['startDate'] === $data['endDate'] && $data['endTime'] <= $data['startTime']) {
            abort(response()->json([
                'error' => 'End time must be greater than start time when start and end date are the same.'
            ], 422));
        }

        return $data;
    }

    /**
     * ✅ Get all screen IDs (explicit + from group)
     */
    private function resolveScreenIds(int $userId, array $data)
    {
        $explicit = collect($data['screens'] ?? [])->pluck('screenId');

        $fromGroup = collect();
        if (!empty($data['group_id'])) {
            $fromGroup = UserScreens::where('user_id', $userId)
                ->where('group_id', $data['group_id'])
                ->pluck('screen_id');
        }

        return $explicit->merge($fromGroup)->filter()->unique()->values();
    }

    /**
     * ✅ Find any screens not owned by the user
     */
    private function findUnownedScreens(int $userId, $screenIds)
    {
        $owned = UserScreens::where('user_id', $userId)
            ->whereIn('screen_id', $screenIds)
            ->pluck('screen_id');

        $notOwned = $screenIds->diff($owned);
        return $notOwned->isNotEmpty() ? $notOwned->values() : null;
    }

    /**
     * ✅ Find conflicts with existing schedules
     */
    private function findConflicts(int $userId, array $data, $screenIds)
    {
        $newStart = Carbon::parse("{$data['startDate']} {$data['startTime']}")->toDateTimeString();
        $newEnd   = Carbon::parse("{$data['endDate']} {$data['endTime']}")->toDateTimeString();

        $conflicting = Schedule::query()
            ->where('user_id', $userId)
            ->whereHas('details', fn ($q) => $q->whereIn('screen_id', $screenIds))
            ->whereRaw("TIMESTAMP(start_day, start_time) < ?", [$newEnd])
            ->whereRaw("TIMESTAMP(end_day,   end_time)   > ?", [$newStart])
            ->with([
                'details'  => fn ($q) => $q->whereIn('screen_id', $screenIds)->select('id', 'schedule_id', 'screen_id'),
                'playlist:id,name',
            ])
            ->get(['id', 'playlist_id', 'group_id', 'start_day', 'end_day', 'start_time', 'end_time']);

        if ($conflicting->isEmpty()) {
            return null;
        }

        $byScreen = [];
        foreach ($conflicting as $sch) {
            foreach ($sch->details as $det) {
                $byScreen[$det->screen_id][] = [
                    'scheduleId'   => $sch->id,
                    'playlistId'   => $sch->playlist_id,
                    'playlistName' => optional($sch->playlist)->name,
                    'startDate'    => $sch->start_day,
                    'endDate'      => $sch->end_day,
                    'startTime'    => $sch->start_time,
                    'endTime'      => $sch->end_time,
                ];
            }
        }
        return $byScreen;
    }

    /**
     * ✅ Create schedule and attach details
     */
    private function createSchedule(int $userId, array $data, $screenIds)
    {
        $schedule = Schedule::create([
            'user_id'     => $userId,
            'playlist_id' => $data['playlistId'],
            'group_id'    => $data['groupId'] ?? null,
            'start_day'   => $data['startDate'],
            'end_day'     => $data['endDate'],
            'start_time'  => $data['startTime'],
            'end_time'    => $data['endTime'],
        ]);

        $rows = $screenIds->map(fn($sid) => [
            'schedule_id' => $schedule->id,
            'screen_id'   => $sid,
        ])->all();

        ScheduleDetails::insert($rows);

        $schedule->loadCount('details');

        return [
            'id'            => $schedule->id,
            'playlist_id'   => $schedule->playlist_id,
            'group_id'      => $schedule->group_id,
            'startDate'     => $schedule->start_day,
            'endDate'       => $schedule->end_day,
            'startTime'     => $schedule->start_time,
            'endTime'       => $schedule->end_time,
            'details_count' => $schedule->details_count,
        ];
    }
  
  
  
  
  
  
public function index(Request $request)
{
    $user = auth()->user();
    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $today = now(config('app.timezone') ?: 'UTC')->toDateString();

    $query = \App\Models\Schedule::query()
        ->where('user_id', $user->id)
        ->whereDate('start_day', '>=', $today)
        ->with(['details:id,schedule_id,screen_id'])
        ->select('id','playlist_id','group_id','start_day','end_day','start_time','end_time')
        ->orderBy('start_day')
        ->orderBy('start_time');

    $groupId  = $request->query('groupId');
    $screenId = $request->query('screenId');

    if (!empty($groupId)) {
        $query->where('group_id', (int) $groupId);
    }

    if (!empty($screenId)) {
        $query->whereHas('details', fn($q) => $q->where('screen_id', (int) $screenId));
    }

    $schedules = $query->get();

    $data = $schedules->map(function ($s) {
        // convert group_id (comma-separated or single) into array of objects
        $groups = collect(explode(',', (string) $s->group_id))
            ->filter()
            ->map(fn($g) => ['groupId' => (int) trim($g)])
            ->values();

        return [
            'id'        => $s->id,
            'groups'    => $groups,
            'startDate' => $s->start_day,
            'endDate'   => $s->end_day,
            'startTime' => $s->start_time,
            'endTime'   => $s->end_time,
            'screens'   => $s->details->map(fn($d) => [
                'screenId' => $d->screen_id,
            ])->values(),
        ];
    })->values();

    return response()->json([
        'success' => true,
        'count'   => $data->count(),
        'data'    => $data,
    ]);
}




  
  
  
  
  
}
