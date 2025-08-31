<?php

namespace App\Http\Controllers\User\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\MediaItem;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\UserPlan;
use App\Models\UserScreens;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class PlayListController extends Controller
{


    public function getscale(){
            return response()->json([
        'success' => true,
        'scale' => [
            '1' => 'fit',
            '2'     => 'fill',
            '3'        => 'blur',
            '4'    => 'original',
        ],
    ]);
    }

// public function getInteractive(Request $request)
// {
//     $tz  = 'Asia/Beirut';
//     $now = Carbon::now($tz);
//     $today = $now->toDateString();

//     $playlists = Playlist::with([
//         'planListStyle:id,type',
//         'playListItems.playListItemStyle:id,type',
//         'playListItems.itemMedia.media:id,type,media',
//         'schedules' // we'll compute on these
//     ])
//     ->where('user_id', 1)
//     ->whereIn('style_id', [2, 3])
//     ->paginate(15);

//     // helper: normalize a schedule window for a given date
//     $makeWindow = function ($schedule, Carbon $baseDay) use ($tz) {
//         $startRaw = (string)$schedule->start_time;
//         $endRaw   = (string)$schedule->end_time;

//         // Heuristic: treat as DATETIME if it contains space or 'T'
//         $isDateTimeStart = str_contains($startRaw, ' ') || str_contains($startRaw, 'T');
//         $isDateTimeEnd   = str_contains($endRaw, ' ')   || str_contains($endRaw, 'T');

//         if ($isDateTimeStart && $isDateTimeEnd) {
//             $start = Carbon::parse($startRaw, $tz);
//             $end   = Carbon::parse($endRaw, $tz);
//         } else {
//             // TIME-only → attach to provided day
//             // allow "HH:MM" or "HH:MM:SS"
//             $startParts = explode(':', $startRaw);
//             $endParts   = explode(':', $endRaw);

//             $start = $baseDay->copy()->setTime(
//                 (int)($startParts[0] ?? 0),
//                 (int)($startParts[1] ?? 0),
//                 (int)($startParts[2] ?? 0)
//             );

//             $end = $baseDay->copy()->setTime(
//                 (int)($endParts[0] ?? 0),
//                 (int)($endParts[1] ?? 0),
//                 (int)($endParts[2] ?? 0)
//             );

//             // Overnight window: if end <= start, push end to next day
//             if ($end->lessThanOrEqualTo($start)) {
//                 $end->addDay();
//             }
//         }

//         return [$start, $end];
//     };

//     $formatted = $playlists->getCollection()->map(function ($playlist) use ($now, $tz, $makeWindow) {
//         $firstItem  = $playlist->playListItems->first();
//         $firstMedia = $firstItem?->itemMedia->first();

//         // LIVE now?
//         $isLive = $playlist->schedules->contains(function ($s) use ($now, $makeWindow) {
//             [$start, $end] = $makeWindow($s, $now->copy()->startOfDay());
//             return $now->between($start, $end);
//         });

//         // Scheduled today?
//         $startOfDay = $now->copy()->startOfDay();
//         $endOfDay   = $now->copy()->endOfDay();

//         $isScheduledToday = $playlist->schedules->contains(function ($s) use ($startOfDay, $endOfDay, $makeWindow) {
//             [$start, $end] = $makeWindow($s, $startOfDay->copy());
//             // Overlap with today's window
//             return $start <= $endOfDay && $end >= $startOfDay;
//         });

//         // Next upcoming window within the next N days (handles TIME-only schedules)
//         $lookAheadDays = 7;
//         $nextStart = null;
//         $nextEnd   = null;

//         for ($i = 0; $i <= $lookAheadDays; $i++) {
//             $day = $now->copy()->startOfDay()->addDays($i);
//             foreach ($playlist->schedules as $s) {
//                 [$start, $end] = $makeWindow($s, $day);
//                 if ($start->greaterThan($now)) {
//                     if (!$nextStart || $start->lessThan($nextStart)) {
//                         $nextStart = $start;
//                         $nextEnd   = $end;
//                     }
//                 }
//             }
//             if ($nextStart) break; // we found the earliest upcoming
//         }

//         return [
//             'id'                 => $playlist->id,
//             'name'               => $playlist->name,
//             'playListStyle'      => $playlist->planListStyle->type ?? null,
//             'duration'           => $playlist->duration,
//             'slide_number'       => $playlist->slide_number,
//             'media'              => $firstMedia?->media?->media ?? null,

//             // NEW
//             'is_live'            => $isLive,
//             'is_scheduled_today' => $isScheduledToday,
//             'next_start'         => $nextStart ? $nextStart->toIso8601String() : null,
//             'next_end'           => $nextEnd   ? $nextEnd->toIso8601String()   : null,
//         ];
//     });

//     $playlists->setCollection($formatted);

//     return response()->json([
//         'success' => true,
//         'playLists' => $playlists->items(),
//         'pagination' => [
//             'current_page' => $playlists->currentPage(),
//             'per_page'     => $playlists->perPage(),
//             'total'        => $playlists->total(),
//             'last_page'    => $playlists->lastPage(),
//         ],
//         'now' => $now->toIso8601String(),
//     ]);
// }



public function getInteractive(Request $request)
{
    $tz  = 'Asia/Beirut';
    $now = Carbon::now($tz);

    // Pagination params (optional): per_page (1–100), page (>=1)
    $perPage = 7;
    $page    = max(1, (int) $request->input('page', 1));

    // Main paginated query (only playlists)
    $playlists = Playlist::with([
            'planListStyle:id,type',
            'playListItems.playListItemStyle:id,type',
            'playListItems.itemMedia.media:id,type,media',
            'schedules:id,playlist_id,screen_id,group_id,start_time,end_time',
        ])
        ->where('user_id', 1)
        ->whereIn('style_id', [2, 3])
        ->orderByDesc('id') 
        ->paginate($perPage, ['*'], 'page', $page);

    // Collect all group_ids used in schedules on THIS PAGE
    $groupIds = [];
    foreach ($playlists->getCollection() as $pl) {
        foreach ($pl->schedules as $s) {
            if (!empty($s->group_id)) $groupIds[] = (int) $s->group_id;
        }
    }
    $groupIds = array_values(array_unique($groupIds));

    // Build map: group_id => [screen_id, ...]  (NO pagination here)
    $groupScreensMap = [];
    if (!empty($groupIds)) {
        $rows = UserScreens::query()
            ->select('group_id', 'screen_id')
            ->whereIn('group_id', $groupIds)
            ->where('user_id', 1)
            ->get();

        foreach ($rows as $r) {
            $gid = (int) $r->group_id;
            $sid = (int) $r->screen_id;
            $groupScreensMap[$gid][] = $sid;
        }

        foreach ($groupScreensMap as $gid => $screens) {
            $groupScreensMap[$gid] = array_values(array_unique($screens));
        }
    }

    // Helper to normalize schedule time windows (handles TIME or DATETIME + overnight)
    $makeWindow = function ($schedule, Carbon $baseDay) use ($tz) {
        $startRaw = (string) $schedule->start_time;
        $endRaw   = (string) $schedule->end_time;

        $isDTs = str_contains($startRaw, ' ') || str_contains($startRaw, 'T');
        $isDTe = str_contains($endRaw,   ' ') || str_contains($endRaw,   'T');

        if ($isDTs && $isDTe) {
            $start = Carbon::parse($startRaw, $tz);
            $end   = Carbon::parse($endRaw,   $tz);
        } else {
            $s = explode(':', $startRaw);
            $e = explode(':', $endRaw);
            $start = $baseDay->copy()->setTime((int)($s[0]??0),(int)($s[1]??0),(int)($s[2]??0));
            $end   = $baseDay->copy()->setTime((int)($e[0]??0),(int)($e[1]??0),(int)($e[2]??0));
            if ($end->lessThanOrEqualTo($start)) $end->addDay(); // overnight
        }
        return [$start, $end];
    };

    $startOfDay = $now->copy()->startOfDay();
    $endOfDay   = $now->copy()->endOfDay();

    // Transform paginated items into your formatted payload
    $playlists->getCollection()->transform(function ($playlist) use ($now, $startOfDay, $endOfDay, $makeWindow, $groupScreensMap) {
        $firstItem  = $playlist->playListItems->first();
        $firstMedia = $firstItem?->itemMedia->first();

        // Unique screens (direct or via groups)
        $uniqueScreens = [];
        foreach ($playlist->schedules as $s) {
            if (!empty($s->screen_id)) {
                $uniqueScreens[(int) $s->screen_id] = true;
            } elseif (!empty($s->group_id)) {
                foreach (($groupScreensMap[$s->group_id] ?? []) as $sid) {
                    $uniqueScreens[(int) $sid] = true;
                }
            }
        }
        $devicesCount = count($uniqueScreens);

        // LIVE now?
        $isLive = $playlist->schedules->contains(function ($s) use ($now, $makeWindow) {
            [$start, $end] = $makeWindow($s, $now->copy()->startOfDay());
            return $now->between($start, $end);
        });

        // Scheduled today?
        $isScheduledToday = $playlist->schedules->contains(function ($s) use ($startOfDay, $endOfDay, $makeWindow) {
            [$start, $end] = $makeWindow($s, $startOfDay->copy());
            return $start <= $endOfDay && $end >= $startOfDay;
        });

        // Next upcoming within 7 days
        $lookAheadDays = 7;
        $nextStart = null; $nextEnd = null;
        for ($i = 0; $i <= $lookAheadDays; $i++) {
            $day = $startOfDay->copy()->addDays($i);
            foreach ($playlist->schedules as $s) {
                [$st, $en] = $makeWindow($s, $day);
                if ($st->greaterThan($now) && (!$nextStart || $st->lt($nextStart))) {
                    $nextStart = $st; $nextEnd = $en;
                }
            }
            if ($nextStart) break;
        }

        return [
            'id'                 => $playlist->id,
            'name'               => $playlist->name,
            'duration'           => $playlist->duration,
            'slide_number'       => $playlist->slide_number,
            'media'              => $firstMedia?->media?->media ?? null,

            'devices_count'      => $devicesCount,
            'is_live'            => $isLive,
            'is_scheduled_today' => $isScheduledToday,
            'next_start'         => $nextStart?->toIso8601String(),
            'next_end'           => $nextEnd?->toIso8601String(),
        ];
    });

    // JSON response with standard pagination meta
    return response()->json([
        'success' => true,
        'playLists' => $playlists->items(), // transformed items for the current page
        'pagination' => [
            'current_page' => $playlists->currentPage(),
            'per_page'     => $playlists->perPage(),
            'total'        => $playlists->total(),
            'last_page'    => $playlists->lastPage(),
            'next_page_url'=> $playlists->nextPageUrl(),
            'prev_page_url'=> $playlists->previousPageUrl(),
            'first_page_url'=> $playlists->url(1),
            'last_page_url' => $playlists->url($playlists->lastPage()),
        ],
        'now' => $now->toIso8601String(),
    ]);
}

public function getNormal(Request $request)
{
    // $user = auth()->user();

    // if (!$user || !$request->user()->tokenCan('user_dashboard')) {
    //     return response()->json(['error' => 'Unauthorized'], 401);
    // }

    $playlists = Playlist::with([
        'planListStyle:id,type',
        'playListItems.playListItemStyle:id,type',
        'playListItems.itemMedia.media:id,type,media'
    ])->where('user_id', 1)->where('style_id',1)->paginate(15);

    $formattedPlaylists = $playlists->map(function ($playlist) {
        $firstItem = $playlist->playListItems->first();
        $firstMedia = $firstItem?->itemMedia->first();

        return [
            'id' => $playlist->id,
            'name' => $playlist->name,
            'playListStyle' => $playlist->planListStyle->type ?? null,
            'duration' => $playlist->duration,
            'slide_number' => $playlist->slide_number,
            'grid' => $firstItem?->playListItemStyle?->type ?? null,
            'media' => $firstMedia?->media?->media ?? null,
        ];
    });

    return response()->json([
        'success' => true,
        'playLists' => $formattedPlaylists,
    ]);
}

public function show(Request $request, $id)
{
    // Optional auth
    // $user = auth()->user();
    // if (!$user || !$request->user()->tokenCan('user_dashboard')) {
    //     return response()->json(['error' => 'Unauthorized'], 401);
    // }

    $playlist = Playlist::with([
        'planListStyle:id,type',
        'playListItems.playListItemStyle:id,type',
        'playListItems.itemMedia.media:id,type,media',
    ])->find($id); // Get the selected playlist by ID

  if (!$playlist) {
    return response()->json([
        'success' => false,
        'message' => 'No Playlist found',
    ], 404);
}
    $formattedPlaylist = [
        'id' => $playlist->id,
        'name' => $playlist->name,
        'duration' => $playlist->duration,
        'slide_number' => $playlist->slide_number,
        'style' => $playlist->planListStyle?->type ?? null,
        'items' => $playlist->playListItems->map(function ($item) {
            return [
                'id' => $item->id,
                'transition' => $item->transition,
                'duration' => $item->duration,
                'index' => $item->index,
                'grid' => $item->playListItemStyle?->type ?? null,
                'mediaItems' => $item->itemMedia->map(function ($mediaItem) {
                    return [
                        'id' => $mediaItem->id,
                        'index' => $mediaItem->index,
                        'scale' => $mediaItem->scale,
                        'media' => [
                            'id' => $mediaItem->media?->id,
                            'type' => $mediaItem->media?->type,
                            'url' => $mediaItem->media?->media,
                        ],
                    ];
                }),
            ];
        }),
    ];

    return response()->json([
        'success' => true,
        'playlist' => $formattedPlaylist,
    ]);
}





public function storeNormal(Request $request)
{
    $request->validate([
        'name'              => 'required|string|max:255', // 🔹 UPDATED: tightened
        'type'              => 'required|integer|exists:playlist_style,id', // 🔹 UPDATED: exists
        'ratio'             => 'required', // (left as-is; see note below)
        'NumberOfSlides'    => 'required|integer|min:1', // 🔹 UPDATED: min
        'total_duration'    => 'required|integer|min:0', // 🔹 UPDATED: min
        'slides'            => 'required|array|min:1',   // 🔹 UPDATED: min
        'slides.*.duration' => 'required|integer|min:0', // 🔹 UPDATED: min
        'slides.*.grid_style' => 'required|integer|exists:list_item_style,id', // 🔹 UPDATED: exists
        'slides.*.index'    => 'required|integer|min:0', // 🔹 UPDATED: min
        'slides.*.slots'    => 'required|array|min:1',   // 🔹 UPDATED: min
        'slides.*.slots.*.index'     => 'required|integer|min:0', // 🔹 UPDATED: min
        'slides.*.slots.*.scale'     => 'required|string', // 🔹 keep; enforce enum if you have it
        'slides.*.slots.*.mediaType' => 'nullable|string|in:image,video',
        'slides.*.slots.*.mediaId'   => 'nullable|integer|exists:media,id',
        // 🔹 UPDATED: local sibling reference in required_without (don’t repeat the full wildcard path)
        'slides.*.slots.*.ImageFile' => 'nullable|required_without:mediaId|file|mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi|max:20480',
    ]);

    // 🔹 UPDATED: quick request integrity checks before starting DB work
    if ((int)$request->NumberOfSlides !== count($request->slides)) {
        return response()->json([
            'success' => false,
            'message' => 'NumberOfSlides does not match slides count.',
        ], 422);
    }
    $sumDur = array_sum(array_map(fn($s) => (int)$s['duration'], $request->slides));
    if ((int)$request->total_duration !== $sumDur) {
        return response()->json([
            'success' => false,
            'message' => 'total_duration does not equal the sum of slide durations.',
        ], 422);
    }

    // Keep the same logging
    Log::info("Request data:\n" . json_encode($request->all(), JSON_PRETTY_PRINT));

    $createdFiles = [];
    $user = auth()->user();

    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    try {
        $playlist = DB::transaction(function () use ($request, &$createdFiles, $user) {

            // Lock user plan to prevent concurrent over-usage
            $userplan = UserPlan::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$userplan) {
                // throw new \RuntimeException('No plan found for user.');
            }

            $usedMB  = (float) ($userplan->used_storage ?? 0); // already used in MB
            $limitMB = (float) ($userplan->storage ?? 0);     // total limit in MB

            // 🔹 UPDATED: safer folder names
            $safeUser     = \Illuminate\Support\Str::slug($user->name);        // 🔹 UPDATED
            $safePlaylist = \Illuminate\Support\Str::slug($request->name);     // 🔹 UPDATED

            // 🔹 NOTE: You accept "ratio" but hardcode ratio_id. Keep as-is, but flag it.
            $playlist = Playlist::create([
                'name'         => $request->name,
                'user_id'      => $user->id,
                'style_id'     => $request->type,
                'ratio_id'     => $request->ratio, // 🔹 TODO: map $request->ratio if needed
                'duration'     => $request->total_duration,
                'slide_number' => $request->NumberOfSlides,
            ]);

            // 🔹 UPDATED: accumulate usage delta; do ONE plan update at the end
            $deltaMB = 0.0; // 🔹 UPDATED

            foreach ($request->slides as $slideIndex => $slideData) {
                $slide = PlaylistItem::create([
                    'playlist_id' => $playlist->id,
                    'duration'    => $slideData['duration'],
                    'grid_id'     => $slideData['grid_style'],
                    'transition'  => 'fade',
                    'index'       => $slideData['index'],
                ]);

                // 🔹 UPDATED: batch insert media items per slide (fewer round-trips)
                $mediaItemRows = []; // 🔹 UPDATED

                foreach ($slideData['slots'] as $slotIndex => $slot) {
                    $media_id = $slot['mediaId'] ?? null;
                    if ($media_id === 'null') {
                        $media_id = null;
                    }

                    if (!$media_id && $request->hasFile("slides.$slideIndex.slots.$slotIndex.ImageFile")) {
                        $file = $request->file("slides.$slideIndex.slots.$slotIndex.ImageFile");

                        // Calculate file size in MB (2 decimals)
                        $fileSizeMB = round($file->getSize() / 1024 / 1024, 2);

                        // Check quota before writing
                        if ($usedMB + $deltaMB + $fileSizeMB > $limitMB) { // 🔹 UPDATED: include pending delta
                            throw new \RuntimeException('You have reached your storage limit');
                        }

                        // 🔹 UPDATED: store under "media/..." and use slugged folders
                        $imageName  = \Illuminate\Support\Str::random(20) . '.' . $file->getClientOriginalExtension();
                        $storedPath = $file->storeAs("media/{$safeUser}/{$safePlaylist}", $imageName, 'public'); // 🔹 UPDATED
                        if (!$storedPath) {
                            throw new \RuntimeException('Failed to store uploaded file.');
                        }

                        $fullPath = storage_path("app/public/{$storedPath}");
                        $createdFiles[] = $fullPath;

                        // Get actual stored size in MB
                        $actualSizeMB = round(filesize($fullPath) / 1024 / 1024, 2);

                        // 🔹 UPDATED: store path in DB (URL can be derived at read time)
                        $createdMedia = Media::create([
                            'type'    => $slot['mediaType'] ?? 'image',
                            'user_id' => $user->id,
                            'media'   => $storedPath, // 🔹 UPDATED: path, not full URL
                            'storage' => $actualSizeMB, // store MB in DB
                        ]);

                        $media_id = $createdMedia->id;

                        // 🔹 UPDATED: only accumulate here; single update after loops
                        $deltaMB += $actualSizeMB; // 🔹 UPDATED
                    }

                    $mediaItemRows[] = [ // 🔹 UPDATED: batch later
                        'playlist_item_id' => $slide->id,
                        'scale'            => $slot['scale'],
                        'index'            => $slot['index'],
                        'media_id'         => $media_id,
                        'created_at'       => now(), // 🔹 UPDATED: set timestamps on bulk insert
                        'updated_at'       => now(),
                    ];
                }

                if (!empty($mediaItemRows)) {
                    MediaItem::insert($mediaItemRows); // 🔹 UPDATED: batch insert
                }
            }

            // 🔹 UPDATED: single plan usage update (reduced lock churn)
            if ($deltaMB > 0) {
                $userplan->update([
                    'used_storage' => $usedMB + $deltaMB, // 🔹 UPDATED
                ]);
            }

            return $playlist;
        });

        return response()->json([
            'success'     => true,
            'message'     => 'Playlist created successfully',
            'playlist_id' => $playlist->id,
        ], 201);

    } catch (\Throwable $e) {
        foreach ($createdFiles as $fullPath) {
            @unlink($fullPath);
        }

        // (optional) Log the exception type for better diagnostics
        Log::error('Playlist create failed: ' . get_class($e) . ' — ' . $e->getMessage()); // 🔹 UPDATED

        return response()->json([
            'success' => false,
            'message' => 'Creation failed. No data was saved.',
            'error'   => config('app.debug') ? $e->getMessage() : null,
        ], 422);
    }
}






public function storeInteractive(Request $request)
{

    $validated = $request->validate([
        'name'          => 'required|string',
        'style_id'      => 'required|integer',
        'slide_number'  => 'required|integer',
        'slides'        => 'required|array',
        'slides.*.index'=> 'required|integer',
        'slides.*.media'    => 'nullable|file', 
        'slides.*.media_id' => 'nullable',
    ]);


    $movedFiles = []; 
    try {
        $playlistId = DB::transaction(function () use ($request, &$movedFiles) {
            $playlist = Playlist::create([
                'name'         => $request->name,
                'user_id'      => 1, // as in your code
                'style_id'     => $request->style_id,
                'duration'     => 0,
                'slide_number' => $request->slide_number,
                'ratio_id' => 1
            ]);

            foreach ($request->slides as $slideIndex => $slideData) {
                $slide = PlaylistItem::create([
                    'playlist_id' => $playlist->id,
                    'duration'    => 0,
                    'index'       => $slideData['index'],
                    'grid_id' => 1
                ]);

               
                $media_id = $slideData['media_id'] ?? null;
                $media_id = ($media_id === 'null') ? null : $media_id;


                if (!$media_id && $request->hasFile("slides.$slideIndex.media")) {
                    $file = $request->file("slides.$slideIndex.media");

                    $imageName = Str::random(20) . '.' . $file->getClientOriginalExtension();
                    $path = public_path("image/omar/{$request->name}");

                    if (!File::exists($path)) {
                        if (!File::makeDirectory($path, 0777, true)) {
                            throw new \RuntimeException('Failed to create destination directory.');
                        }
                    }

        
                    if (!$file->move($path, $imageName)) {
                        throw new \RuntimeException('Failed to move uploaded file.');
                    }

                    $relativePath = "image/omar/{$request->name}/$imageName";
                    $absolutePath = $path . '/' . $imageName;
                    $movedFiles[] = $absolutePath; // track for potential cleanup

                    $imageUrl  = asset($relativePath);
                    $imageSize = round(filesize($absolutePath) / 1024, 2); // KB

                    $createdMedia = Media::create([
                        'type'     => 'image',
                        'user_id'  => 1,
                        'media'    => $imageUrl,
                        'storage'  => $imageSize,
                    ]);

                    $media_id = $createdMedia->id;
                }

                MediaItem::create([
                    'playlist_item_id' => $slide->id,
                    'index'            => 0,
                    'media_id'         => $media_id, // can be null, as per your logic
                ]);
            }

            return $playlist->id;
        }, 3); // retry up to 3 times on deadlocks

        return response()->json([
            'success'     => true,
            'message'     => 'Playlist created successfully',
            'playlist_id' => $playlistId,
        ]);
    } catch (\Throwable $e) {
        // Rollback is automatic. Clean up any files we already moved.
        foreach ($movedFiles as $absPath) {
            if (is_file($absPath)) {
                @unlink($absPath);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to create playlist.',
            'error'   => $e->getMessage(), // for debugging; remove in production
        ], 422);
    }
}



public function getMedia(Request $request){
    $user = auth()->user();

    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $media = Media::where('user_id',$user->id)->paginate(6);

        $formattedPlaylists = $media->map(function ($playlist) {
        return [
            'id' => $playlist->id,
            'type' => $playlist->type,
            'media' => $playlist->media,
            

        ];
    });
    return response()->json(['success' =>true,'media'=>$formattedPlaylists]);


}




}
