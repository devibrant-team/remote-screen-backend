<?php

namespace App\Http\Controllers\User\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Groups;
use App\Models\UserScreens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Str;

class GroupsController extends Controller
{
    public function index (Request $request){
           $user = auth()->user();

    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    $groups = Groups::where('user_id',$user->id)->with(['ratio:id,ratio', 'branch:id,name'])->get();

       $formattedData = $groups->map(function ($playlist) {
        return [
            'id' => $playlist->id,
            'name' => $playlist->name,
            'branchName'   => optional($playlist->branch)->name, // safe if null
                'ratio'        => optional($playlist->ratio)->ratio, // safe if null
            'screenNumber' => $playlist->screen_number,
            
        ];
    });
     return response()->json(['success' => true, 'groups' => $formattedData]);

    }


public function store(Request $request)
{
    try {
        // 1) Validate input
        $data = $request->validate([
            'name'                 => 'required|string',
            'branchId'             => 'required|exists:branches,id',
            'ratioId'              => 'nullable|exists:ratio,id',
            // assignScreens: [{ id: 1 }, { id: 2 }]
            'assignScreens'        => 'sometimes|array',
            'assignScreens.*.screenId'   => 'required_with:assignScreens|integer|distinct|exists:screens,id',
        ]);

        // 2) AuthZ check
        $user = $request->user();
        if (!$user || !$user->tokenCan('user_dashboard')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 3) Transaction: create group & assign screens
        return DB::transaction(function () use ($data, $user) {
            // Create the group
            $group = \App\Models\Groups::create([
                'name'          => $data['name'],
                'branch_id'     => $data['branchId'],
                'ratio_id'      => $data['ratioId'] ?? null,
                'user_id'       => $user->id,
                'screen_number' => 0, // updated below
            ]);

            // Extract screen IDs if provided
            $screenIds = collect($data['assignScreens'] ?? [])
                ->pluck('screenId')
                ->unique()
                ->values();

            if ($screenIds->isNotEmpty()) {
                // (A) Verify screens belong to this user
                $ownedIds = DB::table('user_screens')
                    ->where('user_id', $user->id)
                    ->whereIn('screen_id', $screenIds)
                    ->pluck('screen_id');

                $missing = $screenIds->diff($ownedIds);
                if ($missing->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'assignScreens' => [
                            'One or more screens are not linked to this user: ' . $missing->implode(', ')
                        ],
                    ]);
                }

                // (B) If group has a ratio, enforce screen ratio match
                if (!is_null($group->ratio_id)) {
                    // load each screen's ratio_id
                   $screensWithRatio = DB::table('user_screens as us')
    ->where('us.user_id', $user->id)
    ->whereIn('us.screen_id', $screenIds)
    ->select('us.screen_id', 'us.ratio_id as screen_ratio_id')
    ->get();

                    // find mismatches (null or different)
                    $mismatched = $screensWithRatio
                        ->filter(function ($row) use ($group) {
                            return (int)($row->screen_ratio_id ?? 0) !== (int)$group->ratio_id;
                        })
                        ->pluck('screen_id')
                        ->values();

                    if ($mismatched->isNotEmpty()) {
                        throw ValidationException::withMessages([
                            'assignScreens' => [
                                'These screens do not match the group ratio (must be ratio_id=' . $group->ratio_id . '): ' . $mismatched->implode(', ')
                            ],
                        ]);
                    }
                }

                // (C) Assign the screens to the created group
                DB::table('user_screens')
                    ->where('user_id', $user->id)
                    ->whereIn('screen_id', $screenIds)
                    ->update([
                        'group_id'   => $group->id,
                        'updated_at' => now(),
                    ]);

                // (D) Update group's screen count
                $group->update(['screen_number' => $screenIds->count()]);
            }

            return response()->json([
                'success'              => true,
                'group_id'             => $group->id,
                'assigned_screen_ids'  => $screenIds,
            ], 201);
        });
    } catch (ValidationException $e) {
        return response()->json([
            'message' => 'Validation failed.',
            'errors'  => $e->errors(),
        ], 422);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Something went wrong while creating the group.',
            'error'   => $e->getMessage(),
        ], 500);
    }
}
  
  
  public function update(Request $request, $id)
{
    $request->validate([
        'name' => 'required',
        'branchId' => 'required|exists:branches,id',
        'ratioId' => 'nullable|exists:ratio,id', // ⚡ corrected table name (plural)
    ]);

    $user = auth()->user();

    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    // Find the group or fail
    $group = Groups::where('user_id', $user->id)->findOrFail($id);

    // Update values
    $group->update([
        'name' => $request->name,
        'branch_id' => $request->branchId,
        'ratio_id' => $request->ratioId,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Group updated successfully',
        'data' => $group,
    ]);
}
  
  
  
  
 public function getScreensGroup(Request $request, int $id)
    {
        try {
            // Auth + scope
            $user = $request->user();
            if (!$user || !$user->tokenCan('user_dashboard')) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Eager-load relations.
            // IMPORTANT: include branch_id in 'screen' select if you limit columns.
            $screens = UserScreens::query()
                ->where('user_id', $user->id)
                ->where('group_id', $id)
                ->with([
  'ratio:id,ratio',
  'branch:id,name',
  'screen:id,name',
])
                ->get();

            // Return empty list (not an error) when no records
            if ($screens->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'screens' => [],
                ]);
            }

            // Map to a clean response
            $data = $screens->map(function ($us) {
                return [
                    'id'         => $us->id,
                    'name'       => optional($us->screen)->name,
                    'branchName' => optional(optional($us->branch))->name,
                    'ratio'      =>  optional(optional($us->ratio))->ratio,
                ];
            });

            return response()->json([
                'success' => true,
                'screens' => $data,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error'   => 'Validation failed',
                'details' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            // 👇 Useful for debugging during development; remove message in production
            return response()->json([
                'error'   => 'Server error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

  private function normalizePublicDiskPath(?string $raw): string
{
    if (!$raw) return '';

    // If it's a full URL, keep only the path part
    $urlPath = parse_url($raw, PHP_URL_PATH) ?: $raw;
    $urlPath = ltrim($urlPath, '/'); // strip leading slash

    // If it begins with "storage/", drop it because the symlink already points there
    if (Str::startsWith($urlPath, 'storage/')) {
        $urlPath = Str::after($urlPath, 'storage/');
    }

    // Now accept only paths that start with "file/"
    if (Str::startsWith($urlPath, 'file/')) {
        return $urlPath; // e.g. "file/dxEgoY4Ji5FLc0W9....webm"
    }

    // If the DB held only the filename, prepend "file/" (optional rule)
    // return 'file/' . basename($urlPath);

    // Otherwise, reject unexpected roots for safety
    return '';
}
  
  

    public function publicDownload(Request $request)
{
    $saudi = $request->user();

    if (!$saudi || !$request->user()->currentAccessToken() || $request->user()->currentAccessToken()->name !== 'saudi-api') {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
	
      
      
    $path = Str::after($saudi->media_path, 'storage/');
	
    // Validate path
    if ($path === '' || str_contains($path, '..')) {
        return response()->json(['error' => 'Invalid path'], 400);
    }

    // Check file existence
    if (!Storage::disk('public')->exists($path)) {
        return response()->json(['error' => 'File not found'], 404);
    }

    // Get full absolute path
    $absolute = Storage::disk('public')->path($path);
    $filename = basename($absolute);

    // Return file as a download
    return response()->download(
        $absolute,
        $filename,
        [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]
    );
}

  
  
  


}
