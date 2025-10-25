<?php

namespace App\Http\Controllers\User\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\ItemMedia;

use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\UserPlan;
use App\Models\WidgetDetails;
use App\Models\UserScreens;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlayListController extends Controller
{


    public function getscale()
    {
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




    public function getInteractive(Request $request)
    {
        $user = auth()->user();

        if (!$user || !$request->user()->tokenCan('user_dashboard')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $playlists = Playlist::with([
            'planListStyle:id,type',
            'playListItems.playListItemStyle:id,type',
            'playListItems.itemMedia.media:id,type,media',
        ])
            ->where('user_id', $user->id) // avoid hardcoding 1
            ->whereIn('style_id', [2, 3])
            ->paginate(15);

        // Transform only the items, keep the paginator meta
        $formatted = $playlists->getCollection()->map(function ($playlist) {
            $firstItem  = $playlist->playListItems->first();
            $firstMedia = $firstItem?->itemMedia->first();

            return [
                'id'           => $playlist->id,
                'name'         => $playlist->name,
                'playListStyle' => $playlist->planListStyle->type ?? null,
                'duration'     => $playlist->duration,
                'slide_number' => $playlist->slide_number,
                'grid'         => $firstItem?->playListItemStyle?->type ?? null,
                'media'        => $firstMedia?->media?->media ?? '',
            ];
        });

        // Put the transformed collection back on the paginator (optional)
        $playlists->setCollection($formatted);

        return response()->json([
            'success'    => true,
            'pagination' => [
                'current_page' => $playlists->currentPage(),
                'per_page'     => $playlists->perPage(),
                'total'        => $playlists->total(),
                'last_page'    => $playlists->lastPage(),
                'from'         => $playlists->firstItem(),
                'to'           => $playlists->lastItem(),
            ],
            // Many frontends prefer a flat array of items
            'playLists'  => $formatted->values(),
            // If your frontend likes full Laravel-style pagination, you could instead send:
            // 'data' => $formatted->values(),
            // 'links' => $playlists->linkCollection(), // if you need links
        ]);
    }







    public function show(Request $request, $id)
    {


        // Optional auth
        $user = auth()->user();
        if (!$user || !$request->user()->tokenCan('user_dashboard')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $playlist = Playlist::with([
            'planListStyle:id,type',
            'playListItems.playListItemStyle:id,type',
            'playListItems.itemMedia.media:id,type,media',
            'playListItems.itemMedia.widget:id,type,city,position',
        ])->find($id); // Get the selected playlist by ID

        if (!$playlist) {
            return response()->json([
                'success' => false,
                'message' => 'No Playlist found',
            ], 404);
        }

        $formattedPlaylist = [];
        if ($playlist->style_id === 2 || $playlist->style_id === 3) {
            $formattedPlaylist = [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'slide_number' => $playlist->slide_number,
                'style' => $playlist->planListStyle?->type ?? null,
                'slides' => $playlist->playListItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'index' => $item->index,
                        'media' => $item->itemMedia[0]->media?->media,
                        'media_id' => $item->itemMedia[0]->media?->id,
                    ];
                }),
            ];
        } else {
            $formattedPlaylist = [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'duration' => $playlist->duration,
                'slide_number' => $playlist->slide_number,
                'style' => $playlist->planListStyle?->type ?? null,
                'slides' => $playlist->playListItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'transition' => $item->transition,
                        'duration' => $item->duration,
                        'index' => $item->index,
                        'grid_style' => $item->playListItemStyle?->id ?? null,
                        'slots' => $item->itemMedia->map(function ($ItemMedia) {
                            return [
                                'id' => $ItemMedia->id,
                                'index' => $ItemMedia->index,
                                'scale' => $ItemMedia->scale,
                                'mediaType' => $ItemMedia->media?->type,
                                'mediaId' => $ItemMedia->media?->id,
                                'ImageFile' => $ItemMedia->media?->media,
                                'widget' => $ItemMedia->widget
                        ? [
                            'id' => $ItemMedia->widget->id,
                            'type' => $ItemMedia->widget->type,
                            'city' => $ItemMedia->widget->city,
                            'position' => $ItemMedia->widget->position,
                        ]
                        : null,
                            ];
                        }),
                    ];
                }),
            ];
        }



        return response()->json([
            'success' => true,
            'playlist' => $formattedPlaylist,
        ]);
    }






    public function storeInteractive(Request $request)
    {
        // ✅ Validation aligned with storeNormal
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'style_id'      => 'required|integer|exists:playlist_style,id',
            'slide_number'  => 'required|integer|min:1',
            'ratio'         => 'nullable|integer|exists:ratio,id', // optional; default below
            'slides'        => 'required|array|min:1',
            'slides.*.index'    => 'required|integer|min:0',
            'slides.*.media_id' => 'nullable|integer|exists:media,id',
            // local sibling reference for required_without
            'slides.*.media'    => 'nullable|required_without:slides.*.media_id|file|mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi|max:20480',
        ]);

        $user = auth()->user();
        if (!$user || !$request->user()->tokenCan('user_dashboard')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // For filesystem cleanup on failure
        $createdFiles = [];

        try {
            $playlist = DB::transaction(function () use ($request, $user, &$createdFiles) {

                // 🔒 Lock plan row to prevent concurrent overage
                $userplan = UserPlan::where('user_id', $user->id)->lockForUpdate()->first();
                if (!$userplan) {
                    
                     throw new \RuntimeException('No plan found for user.');
                }

                $usedMB  = (float) ($userplan->used_storage ?? 0);
                $limitMB = (float) ($userplan->storage ?? 0);
                $deltaMB = 0.0; // accumulate and write once at the end

                // Safe folder names
                $safeUser     = \Illuminate\Support\Str::slug($user->name);
                $safePlaylist = \Illuminate\Support\Str::slug($request->name);

                // Create playlist
                $playlist = Playlist::create([
                    'name'         => $request->name,
                    'user_id'      => $user->id,
                    'style_id'     => $request->style_id,
                    'duration'     => 0,
                    'slide_number' => $request->slide_number,
                    'ratio_id'     => $request->input('ratio', 1), // keep behavior; default 1
                ]);

                foreach ($request->slides as $slideIndex => $slideData) {
                    $slide = PlaylistItem::create([
                        'playlist_id' => $playlist->id,
                        'duration'    => 0,
                        'index'       => $slideData['index'],
                        'grid_id'     => 1, // keep your current default
                        'transition'  => 'fade',
                    ]);

                    // media_id can be null or numeric
                    $media_id = $slideData['media_id'] ?? null;
                    if ($media_id === 'null') {
                        $media_id = null;
                    }

                    // If no media_id, try to store uploaded file (public disk)
                    if (!$media_id && $request->hasFile("slides.$slideIndex.media")) {
                        $file = $request->file("slides.$slideIndex.media");

                        // Check quota vs actual on disk size (approx via uploaded file first)
                        $fileSizeMB = round($file->getSize() / 1024 / 1024, 2);
                        if ($userplan && ($usedMB + $deltaMB + $fileSizeMB > $limitMB)) {
                            throw new \RuntimeException('You have reached your storage limit');
                        }

                        $imageName  = \Illuminate\Support\Str::random(20) . '.' . $file->getClientOriginalExtension();
                        $storedPath = $file->storeAs("media/{$safeUser}-{$user->id}/interactive/{$safePlaylist}", $imageName, 'public');
                        if (!$storedPath) {
                            throw new \RuntimeException('Failed to store uploaded file.');
                        }

                        // Track physical file for cleanup
                        $fullPath = storage_path("app/public/{$storedPath}");
                        $createdFiles[] = $fullPath;

                        // Build URL and get actual stored size
                        $fullUrl      = asset('storage/' . $storedPath);
                        $actualSizeMB = round(filesize($fullPath) / 1024 / 1024, 3);

                        // Decide media type by mime
                        $mime   = $file->getMimeType();
                        $mtype  = str_starts_with($mime, 'video/') ? 'video' : 'image';

                        $createdMedia = Media::create([
                            'type'    => $mtype,
                            'user_id' => $user->id,
                            'media'   => $fullUrl,   // storing URL like in storeNormal
                            'storage' => $actualSizeMB,
                        ]);

                        $media_id = $createdMedia->id;
                        $deltaMB += $actualSizeMB;
                    }

                    // Create ItemMedia (single slot for interactive)
                    ItemMedia::create([
                        'playlist_item_id' => $slide->id,
                        'index'            => 0,
                        'scale'            => 'normal', // optional; set if you have a default
                        'media_id'         => $media_id, // nullable ok
                    ]);
                }

                // Single storage usage write
                if ($userplan && $deltaMB > 0) {
                    $userplan->update([
                        'used_storage' => $usedMB + $deltaMB,
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
            // Cleanup stored files if any
            foreach ($createdFiles as $fullPath) {
                @unlink($fullPath);
            }

            Log::error('Interactive playlist create failed: ' . get_class($e) . ' — ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create playlist.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 422);
        }
    }







    public function updateInteractive(Request $request, int $playlistId)
    {
        // ✅ Validation
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'style_id'      => 'required|integer|exists:playlist_style,id',
            'slide_number'  => 'required|integer|min:1',
            'ratio'         => 'nullable|integer|exists:ratio,id',
            'slides'        => 'required|array|min:1',

            // Existing or new slides
            'slides.*.id'        => 'nullable|integer|exists:playlist_item,id',
            'slides.*.index'     => 'required|integer|min:0',

            // Media handling (either reuse existing media_id or upload a new file)
            'slides.*.media_id'  => 'nullable|integer|exists:media,id',
            'slides.*.media'     => 'nullable|required_without:slides.*.media_id|file|mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi|max:20480',
        ]);

        $user = auth()->user();
        if (!$user || !$request->user()->tokenCan('user_dashboard')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Fetch playlist & check ownership
        $playlist = Playlist::with(['playListItems.itemMedia'])->findOrFail($playlistId);
        if ((int)$playlist->user_id !== (int)$user->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Files to clean up if the transaction fails
        $createdFiles = [];

        try {
            DB::transaction(function () use ($request, $user, $playlist, &$createdFiles) {
                // 🔒 Lock user plan
                $userplan = UserPlan::where('user_id', $user->id)->lockForUpdate()->first();
                $usedMB   = (float) ($userplan->used_storage ?? 0);
                $limitMB  = (float) ($userplan->storage ?? 0);
                $deltaMB  = 0.0;

                // Safe folder names
                $safeUser     = \Illuminate\Support\Str::slug($user->name);
                $safePlaylist = \Illuminate\Support\Str::slug($request->name);

                // Update high-level playlist info
                $playlist->update([
                    'name'         => $request->name,
                    'style_id'     => $request->style_id,
                    'slide_number' => $request->slide_number,
                    'ratio_id'     => $request->input('ratio', $playlist->ratio_id ?? 1),
                    // duration stays 0 for interactive unless you compute it elsewhere
                ]);

                // Build a map of existing slides for quick lookup
                $existingItems = $playlist->playListItems->keyBy('id'); // id => PlaylistItem

                // Collect incoming IDs (for deletion of removed slides)
                $incomingIds = collect($request->slides)
                    ->pluck('id')
                    ->filter()
                    ->map(fn($v) => (int)$v)
                    ->values()
                    ->all();

                // Determine which existing slides should be deleted
                $toDeleteIds = $existingItems
                    ->keys()
                    ->diff($incomingIds)
                    ->values()
                    ->all();

                // 🚮 Delete removed slides' rows (PlaylistItem + ItemMedia) BUT NOT Media
                if (!empty($toDeleteIds)) {
                    // First delete item_media rows referencing these playlist items
                    ItemMedia::whereIn('playlist_item_id', $toDeleteIds)->delete();
                    // Then delete the playlist items
                    PlaylistItem::whereIn('id', $toDeleteIds)->delete();
                }

                // Upsert slides
                foreach ($request->slides as $idx => $slideData) {
                    $slideId   = $slideData['id'] ?? null;
                    $mediaId   = $slideData['media_id'] ?? null;
                    if ($mediaId === 'null') $mediaId = null;

                    // Create or update slide
                    if ($slideId && isset($existingItems[$slideId])) {
                        // Update existing slide
                        $slide = $existingItems[$slideId];
                        $slide->update([
                            'index'       => $slideData['index'],
                            'duration'    => 0,
                            'grid_id'     => $slide->grid_id ?? 1,
                            'transition'  => $slide->transition ?? 'fade',
                        ]);
                    } else {
                        // New slide
                        $slide = PlaylistItem::create([
                            'playlist_id' => $playlist->id,
                            'index'       => $slideData['index'],
                            'duration'    => 0,
                            'grid_id'     => 1,
                            'transition'  => 'fade',
                        ]);
                    }

                    // Find or create the single ItemMedia row for this interactive slide (index 0)
                    $itemMedia = ItemMedia::where('playlist_item_id', $slide->id)
                        ->orderBy('index')
                        ->first();

                    // If user uploaded a new file, store it and create a NEW Media record
                    if (!$mediaId && $request->hasFile("slides.$idx.media")) {
                        $file = $request->file("slides.$idx.media");

                        // Quota check (preliminary by uploaded file size)
                        $fileSizeMB = round($file->getSize() / 1024 / 1024, 2);
                        if ($userplan && ($usedMB + $deltaMB + $fileSizeMB > $limitMB)) {
                            throw new \RuntimeException('You have reached your storage limit');
                        }

                        // Save to public disk under media/{user}/{playlist}
                        $imageName  = \Illuminate\Support\Str::random(20) . '.' . $file->getClientOriginalExtension();
                        $storedPath = $file->storeAs("media/{$safeUser}-{$user->id}/interactive/{$safePlaylist}", $imageName, 'public');
                        if (!$storedPath) {
                            throw new \RuntimeException('Failed to store uploaded file.');
                        }

                        $fullPath     = storage_path("app/public/{$storedPath}");
                        $createdFiles[] = $fullPath; // in case we need to roll back
                        $fullUrl      = asset('storage/' . $storedPath);

                        // Recalculate actual stored size
                        $actualSizeMB = round(filesize($fullPath) / 1024 / 1024, 2);
                        $deltaMB     += $actualSizeMB;

                        $mime  = $file->getMimeType();
                        $mtype = str_starts_with($mime, 'video/') ? 'video' : 'image';

                        $newMedia = Media::create([
                            'type'    => $mtype,
                            'user_id' => $user->id,
                            'media'   => $fullUrl,
                            'storage' => $actualSizeMB,
                        ]);

                        // Use the new media id; DO NOT delete any old media
                        $mediaId = $newMedia->id;
                    }

                    // Update or create ItemMedia row with the (possibly new) media_id
                    if ($itemMedia) {
                        // Only re-point; do not delete old media rows
                        $itemMedia->update([
                            'index'    => 0,
                            'scale'    => $itemMedia->scale ?? 'normal',
                            'media_id' => $mediaId, // can be null (keeps no-media)
                        ]);
                    } else {
                        ItemMedia::create([
                            'playlist_item_id' => $slide->id,
                            'index'            => 0,
                            'scale'            => 'normal',
                            'media_id'         => $mediaId, // can be null
                        ]);
                    }
                }

                // Single write for storage usage
                if ($userplan && $deltaMB > 0) {
                    $userplan->update([
                        'used_storage' => $usedMB + $deltaMB,
                    ]);
                }
            }, 3);

            return response()->json([
                'success' => true,
                'message' => 'Interactive playlist updated successfully',
            ], 200);
        } catch (\Throwable $e) {
            // Clean up any newly created files (only if the DB tx fails)
            foreach ($createdFiles as $fullPath) {
                @unlink($fullPath);
            }

            Log::error('Interactive playlist update failed: ' . get_class($e) . ' — ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update interactive playlist.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 422);
        }
    }





    public function getMedia(Request $request)
    {
        $user = auth()->user();

        if (!$user || !$request->user()->tokenCan('user_dashboard')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $media = Media::where('user_id', $user->id)->paginate(6);

        $formattedPlaylists = $media->map(function ($playlist) {
            return [
                'id' => $playlist->id,
                'type' => $playlist->type,
                'media' => $playlist->media,


            ];
        });
        return response()->json(['success' => true, 'media' => $formattedPlaylists]);
    }
  
  
  
  
  
  
   public function destroy(Request $request ,$id){
     
         $user = auth()->user();
        if (!$user || !$request->user()->tokenCan('user_dashboard')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $playList = Playlist::find($id);
        if(!$playList){
            return response()->json(['error' => 'playList not found'], 404);
        }
        $playList->delete();
        return response()->json(['success' => true]);
     
      }
  
  
  
}
