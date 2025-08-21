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

    $playlists = Playlist::with([
        'planListStyle:id,type',
        'playListItems.playListItemStyle:id,type',
        'playListItems.itemMedia.media:id,type,media',
        'schedules:id,playlist_id,screen_id,group_id,start_time,end_time', // pull only needed cols
    ])
    ->where('user_id', 1)
    ->whereIn('style_id', [2, 3])
    ->paginate(15);

    // Collect all group_ids used in schedules on this page
    $groupIds = [];
    foreach ($playlists->getCollection() as $pl) {
        foreach ($pl->schedules as $s) {
            if (!empty($s->group_id)) $groupIds[] = $s->group_id;
        }
    }
    $groupIds = array_values(array_unique($groupIds));

    // Build a map: group_id => array of screen_ids (for this user)
    $groupScreensMap = [];
    if (!empty($groupIds)) {
        // If you want to restrict by user, add ->where('user_id', 1)
        $rows = UserScreens::query()
            ->select('group_id', 'screen_id')
            ->whereIn('group_id', $groupIds)
            ->where('user_id', 1) // keep devices belonging to this user
            ->get();

        foreach ($rows as $r) {
            $groupScreensMap[$r->group_id][] = (int)$r->screen_id;
        }

        // Deduplicate screen ids per group
        foreach ($groupScreensMap as $gid => $screens) {
            $groupScreensMap[$gid] = array_values(array_unique($screens));
        }
    }

    // Helper to normalize schedule time windows (handles TIME or DATETIME + overnight)
    $makeWindow = function ($schedule, Carbon $baseDay) use ($tz) {
        $startRaw = (string)$schedule->start_time;
        $endRaw   = (string)$schedule->end_time;
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

    $formatted = $playlists->getCollection()->map(function ($playlist) use ($now, $startOfDay, $endOfDay, $makeWindow, $groupScreensMap) {
        $firstItem  = $playlist->playListItems->first();
        $firstMedia = $firstItem?->itemMedia->first();

        // Build unique screen set for this playlist across all its schedules
        $uniqueScreens = [];

        foreach ($playlist->schedules as $s) {
            if (!empty($s->screen_id)) {
                $uniqueScreens[(int)$s->screen_id] = true;
            } elseif (!empty($s->group_id)) {
                foreach ($groupScreensMap[$s->group_id] ?? [] as $sid) {
                    $uniqueScreens[(int)$sid] = true;
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
            'playListStyle'      => $playlist->planListStyle->type ?? null,
            'duration'           => $playlist->duration,
            'slide_number'       => $playlist->slide_number,
            'media'              => $firstMedia?->media?->media ?? null,

            // NEW
            'devices_count'      => $devicesCount,   // ✅ number of unique devices scheduled to this playlist
            'is_live'            => $isLive,
            'is_scheduled_today' => $isScheduledToday,
            'next_start'         => $nextStart?->toIso8601String(),
            'next_end'           => $nextEnd?->toIso8601String(),
        ];
    });

    $playlists->setCollection($formatted);

    return response()->json([
        'success' => true,
        'playLists' => $playlists->items(),
        'pagination' => [
            'current_page' => $playlists->currentPage(),
            'per_page'     => $playlists->perPage(),
            'total'        => $playlists->total(),
            'last_page'    => $playlists->lastPage(),
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
        'name' => 'required|string',
        'type' => 'required|integer',
        'ratio' => 'nullable',
        'NumberOfSlides' => 'required|integer',
        'total_duration' => 'required|integer',
        'slides' => 'required|array',
        'slides.*.duration' => 'required|integer',
        'slides.*.grid_style' => 'required|integer',
        'slides.*.index' => 'required|integer',
        'slides.*.slots' => 'required|array',
        'slides.*.slots.*.index' => 'required|integer',
        'slides.*.slots.*.scale' => 'required|string',
        'slides.*.slots.*.mediaType' => 'nullable|string|in:image,video',
        'slides.*.slots.*.mediaId' => 'nullable|integer|exists:media,id',
        'slides.*.slots.*.ImageFile' => 'nullable|required_without:slides.*.slots.*.mediaId|file|mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi|max:20480',
    ]);
Log::info(
    "Request data:\n" . json_encode($request->all(), JSON_PRETTY_PRINT)
);

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

            $usedMB = (float) ($userplan->used_storage ?? 0); // already used in MB
            $limitMB = (float) ($userplan->storage ?? 0);     // total limit in MB

            $playlist = Playlist::create([
                'name'         => $request->name,
                'user_id'      => $user->id,
                'style_id'     => $request->type,
                'ratio_id'     =>1,
                'duration'     => $request->total_duration,
                'slide_number' => $request->NumberOfSlides,
            ]);

            foreach ($request->slides as $slideIndex => $slideData) {
                $slide = PlaylistItem::create([
                    'playlist_id' => $playlist->id,
                    'duration'    => $slideData['duration'],
                    'grid_id'     => $slideData['grid_style'],
                    'transition'  => 'fade',
                    'index'       => $slideData['index'],
                ]);

                foreach ($slideData['slots'] as $slotIndex => $slot) {
                    $media_id = $slot['mediaId'] ?? null;
                    if ($media_id === 'null') $media_id = null;

                    if (!$media_id && $request->hasFile("slides.$slideIndex.slots.$slotIndex.ImageFile")) {
                        $file = $request->file("slides.$slideIndex.slots.$slotIndex.ImageFile");

                        // Calculate file size in MB (2 decimals)
                        $fileSizeMB = round($file->getSize() / 1024 / 1024, 2);

                        // Check quota before writing
                        if ($usedMB + $fileSizeMB > $limitMB) {
                            throw new \RuntimeException('You have reached your storage limit');
                        }

                        // Store file
                        $imageName  = Str::random(20) . '.' . $file->getClientOriginalExtension();
                        $storedPath = $file->storeAs("image/{$user->name}/{$request->name}", $imageName, 'public');
                        if (!$storedPath) {
                            throw new \RuntimeException('Failed to store uploaded file.');
                        }

                        $fullPath = storage_path("app/public/{$storedPath}");
                        $createdFiles[] = $fullPath;

                        // Get actual stored size in MB
                        $actualSizeMB = round(filesize($fullPath) / 1024 / 1024, 2);

                        $imageUrl = asset("storage/{$storedPath}");

                        $createdMedia = Media::create([
                            'type'      => $slot['mediaType'] ?? 'image',
                            'user_id'   => $user->id,
                            'media'     => $imageUrl,
                            'storage'   => $actualSizeMB, // store MB in DB
                        ]);

                        $media_id = $createdMedia->id;

                        // Update usage
                        $usedMB += $actualSizeMB;
                        $userplan->update([
                            'used_storage' => $usedMB
                        ]);
                    }

                    MediaItem::create([
                        'playlist_item_id' => $slide->id,
                        'scale'            => $slot['scale'],
                        'index'            => $slot['index'],
                        'media_id'         => $media_id,
                    ]);
                }
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
