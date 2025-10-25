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

class NormalPlayListController extends Controller
{
    
  
   public function getNormal(Request $request)
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
        ->where('style_id', 1)
        ->paginate(15);

    // Transform only the items, keep the paginator meta
    $formatted = $playlists->getCollection()->map(function ($playlist) {
        $firstItem  = $playlist->playListItems->first();
        $firstMedia = $firstItem?->itemMedia->first();

        return [
            'id'           => $playlist->id,
            'name'         => $playlist->name,
            'playListStyle'=> $playlist->planListStyle->type ?? null,
            'duration'     => $playlist->duration,
            'slide_number' => $playlist->slide_number,
            'grid'         => $firstItem?->playListItemStyle?->type ?? null,
            'media'        => $firstMedia?->media?->media ?? null,
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
            'slides.*.slots.*.ImageFile' => 'nullable|required_without:slides.*.slots.*.mediaId|file|mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi|max:20480',
            'slides.*.slots.*.widget'               => 'nullable|array',
            'slides.*.slots.*.widget.type'          => 'nullable|string',
            'slides.*.slots.*.widget.position'      => 'nullable|string',
            'slides.*.slots.*.widget.city'          => 'nullable|string',
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
                    $ItemMediaRows = []; // 🔹 UPDATED

                    foreach ($slideData['slots'] as $slotIndex => $slot) {
                        $media_id = $slot['mediaId'] ?? null;

                        if ($media_id === 'null') {
                            $media_id = null;
                        }

                        $widgetData = $slot['widget'] ?? null;
                        $widgetId = null;

                        if (is_array($widgetData)) {

                            $widget = WidgetDetails::create([
                                'type'     => $widgetData['type']     ?? null,
                                'position' => $widgetData['position'] ?? null,
                                'city'     => $widgetData['city']     ?? null,
                            ]);
                            $widgetId = $widget->id;
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
                            $storedPath = $file->storeAs("media/{$safeUser}-{$user->id}/normal/{$safePlaylist}", $imageName, 'public'); // 🔹 UPDATED
                            if (!$storedPath) {
                                throw new \RuntimeException('Failed to store uploaded file.');
                            }

                            $fullPath = storage_path("app/public/{$storedPath}");
                            $createdFiles[] = $fullPath;
                            $fullUrl = asset('storage/' . $storedPath);
                            // Get actual stored size in MB
                            $actualSizeMB = round(filesize($fullPath) / 1024 / 1024, 2);


                            // 🔹 UPDATED: store path in DB (URL can be derived at read time)
                            $createdMedia = Media::create([
                                'type'    => $slot['mediaType'] ?? 'image',
                                'user_id' => $user->id,
                                'media'   => $fullUrl, // 🔹 UPDATED: path, not full URL
                                'storage' => $actualSizeMB, // store MB in DB
                            ]);

                            $media_id = $createdMedia->id;

                            // 🔹 UPDATED: only accumulate here; single update after loops
                            $deltaMB += $actualSizeMB; // 🔹 UPDATED
                        }

                        $ItemMediaRows[] = [ // 🔹 UPDATED: batch later
                            'playlist_item_id' => $slide->id,
                            'scale'            => $slot['scale'],
                            'index'            => $slot['index'],
                            'media_id'         => $media_id,
                            'widget_id'           => $widgetId,
                            'created_at'       => now(), // 🔹 UPDATED: set timestamps on bulk insert
                            'updated_at'       => now(),
                        ];
                    }

                    if (!empty($ItemMediaRows)) {
                        ItemMedia::insert($ItemMediaRows); // 🔹 UPDATED: batch insert
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
  
  
  
  
  
  
   public function updateNormal(Request $request, int $playlistId)
    {
			
        // ✅ Validation mirrors storeNormal, with optional IDs for existing rows
        $request->validate([

            'name' => 'required|string|max:255',
            'type' => 'required|integer|exists:playlist_style,id',
            'ratio' => 'required',
            'NumberOfSlides' => 'required|integer|min:1',
            'total_duration' => 'required|integer|min:0',
            'slides' => 'required|array|min:1',

            'slides.*.id' => 'nullable|integer|exists:playlist_item,id', // existing slide id (optional)
            'slides.*.duration' => 'required|integer|min:0',
            'slides.*.grid_style' => 'required|integer|exists:list_item_style,id',
            'slides.*.index' => 'required|integer|min:0',
            'slides.*.slots' => 'required|array|min:1',

            'slides.*.slots.*.id' => 'nullable|integer|exists:item_media,id', // existing slot id (optional)
            'slides.*.slots.*.index' => 'required|integer|min:0',
            'slides.*.slots.*.scale' => 'required|string',
            'slides.*.slots.*.mediaType' => 'nullable|string|in:image,video',
            'slides.*.slots.*.mediaId' => 'nullable|integer|exists:media,id',
            // Sibling reference: don't repeat wildcard path
            'slides.*.slots.*.ImageFile' =>
            'nullable|required_without:slides.*.slots.*.mediaId|file|mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi|max:20480',

            'slides.*.slots.*.widget'            => 'nullable',           // can be null or array
            'slides.*.slots.*.widget.id'         => 'nullable|integer|exists:widget_details,id',
            'slides.*.slots.*.widget.type'       => 'nullable|string|max:50',
            'slides.*.slots.*.widget.city'       => 'nullable|string|max:255',
            'slides.*.slots.*.widget.position'   => 'nullable|string|max:64',
        ]);

        // 🔍 Integrity checks
        if ((int) $request->NumberOfSlides !== count($request->slides ?? [])) {
            return response()->json(['success' => false, 'message' => 'NumberOfSlides does not match slides count.'], 422);
        }
        $sumDur = array_sum(array_map(fn($s) => (int)($s['duration']), $request->slides));
        if ((int) $request->total_duration !== $sumDur) {
            return response()->json(
                ['success' => false, 'message' => 'total_duration does not equal the sum of slide durations.'],
                422
            );
        }

        $createdFiles = [];
        $user = auth()->user();
        if (!$user || !$request->user()->tokenCan('user_dashboard')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Fetch playlist & assert ownership
        $playlist = Playlist::with(['playListItems.itemMedia'])
            ->where('id', $playlistId)
            ->where('user_id', $user->id)
            ->first();

        if (!$playlist) {
            return response()->json(['success' => false, 'message' => 'Playlist not found.'], 404);
        }

        $replaceMissing = $request->boolean('replace_missing', true);

        try {
            DB::transaction(function () use ($request, $user, $playlist, &$createdFiles, $replaceMissing) {
                // 🔒 Lock plan
                $userplan = UserPlan::where('user_id', $user->id)->lockForUpdate()->first();
                $usedMB = (float) ($userplan->used_storage ?? 0);
                $limitMB = (float) ($userplan->storage ?? 0);
                $deltaAddMB = 0.0;

                $safeUser = Str::slug($user->name);
                $safePlaylist = Str::slug($request->name);

                // 1) Update playlist
                $playlist->update([
                    'name' => $request->name,
                    'style_id' => $request->type,
                    'ratio_id' => $request->ratio,
                    'duration' => $request->total_duration,
                    'slide_number' => $request->NumberOfSlides,
                ]);

                // Maps
                $existingSlides = $playlist->playListItems()->get()->keyBy('id');
                $existingSlotsBySlide = [];
                foreach ($playlist->playListItems as $it) {
                    $existingSlotsBySlide[$it->id] = $it->itemMedia->keyBy('id');
                }

                $keepSlideIds = [];
                $keepSlotIds = [];

                foreach ($request->slides as $slideIndex => $slideData) {
                    $slideId = $slideData['id'] ?? null;

                    if ($slideId && isset($existingSlides[$slideId])) {
                        $slide = $existingSlides[$slideId];
                        $slide->update([
                            'duration' => (int) $slideData['duration'],
                            'grid_id' => (int) $slideData['grid_style'],
                            'transition' => 'fade',
                            'index' => (int) $slideData['index'],
                        ]);
                    } else {
                        $slide = PlaylistItem::create([
                            'playlist_id' => $playlist->id,
                            'duration' => (int) $slideData['duration'],
                            'grid_id' => (int) $slideData['grid_style'],
                            'transition' => 'fade',
                            'index' => (int) $slideData['index'],
                        ]);
                    }
                    $keepSlideIds[] = $slide->id;

                    // Slots
                    $slotMap = $existingSlotsBySlide[$slide->id] ?? collect();
                    $rowsToInsert = [];

                    foreach ($slideData['slots'] as $slotIndex => $slot) {
                        $slotId = $slot['id'] ?? null;
                        $media_id = $slot['mediaId'] ?? null;
                        if ($media_id === 'null') {
                            $media_id = null;
                        }

                        // Upload new file → create Media
                        $fileKey = "slides.$slideIndex.slots.$slotIndex.ImageFile";
                        if (!$media_id && $request->hasFile($fileKey)) {
                            $file = $request->file($fileKey);

                            $fileSizeMB = round($file->getSize() / 1024 / 1024, 2);
                            if ($userplan && $limitMB > 0 && ($usedMB + $deltaAddMB + $fileSizeMB > $limitMB)) {
                                throw new \RuntimeException('You have reached your storage limit');
                            }

                            $imageName = Str::random(20) . '.' . $file->getClientOriginalExtension();
                            $storedPath = $file->storeAs("media/{$safeUser}-{$user->id}/normal/{$safePlaylist}", $imageName, 'public');
                            if (!$storedPath) {
                                throw new \RuntimeException('Failed to store uploaded file.');
                            }

                            $fullPath = storage_path("app/public/{$storedPath}");
                            $createdFiles[] = $fullPath;
                            $fullUrl = asset('storage/' . $storedPath);
                            $actualSizeMB = round(filesize($fullPath) / 1024 / 1024, 2);

                            $createdMedia = Media::create([
                                'type' => $slot['mediaType'] ?? 'image',
                                'user_id' => $user->id,
                                'media' => $fullUrl,
                                'storage' => $actualSizeMB,
                            ]);

                            $media_id = $createdMedia->id;
                            $deltaAddMB += $actualSizeMB;
                        }

                        // ⬇ Widget upsert/link
                        $widgetPayload = $slot['widget'] ?? null;
                        $widgetId = null;

                        if (is_array($widgetPayload)) {
                            if (!empty($widgetPayload['id'])) {
                                $widget = WidgetDetails::lockForUpdate()->find($widgetPayload['id']);
                                if ($widget) {
                                    $widget->fill([
                                        'type' => $widgetPayload['type'] ?? $widget->type,
                                        'city' => $widgetPayload['city'] ?? $widget->city,
                                        'position' => $widgetPayload['position'] ?? $widget->position,
                                    ])->save();
                                    $widgetId = $widget->id;
                                }
                            } else {
                                $widget = WidgetDetails::create([
                                    'type' => $widgetPayload['type'],
                                    'city' => $widgetPayload['city'],
                                    'position' => $widgetPayload['position'],
                                ]);
                                $widgetId = $widget->id;
                            }
                        }
                        // ⬆ Widget upsert/link

                        // Upsert slot
                        if ($slotId && $slotMap->has($slotId)) {
                            $slotRow = $slotMap[$slotId];

                            $updateData = [
                                'scale' => $slot['scale'],
                                'index' => (int) $slot['index'],
                                'media_id' => $media_id,
                            ];
                            if (array_key_exists('widget', $slot)) {
                                $updateData['widget_id'] = $widgetId; // explicit set/clear
                            }

                            $slotRow->update($updateData);
                            $keepSlotIds[] = $slotRow->id;
                        } else {
                            $rowsToInsert[] = [
                                'playlist_item_id' => $slide->id,
                                'scale' => $slot['scale'],
                                'index' => (int) $slot['index'],
                                'media_id' => $media_id,
                                'widget_id' => $widgetId,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }

                    if (!empty($rowsToInsert)) {
                        ItemMedia::insert($rowsToInsert);

                        $insertedIds = ItemMedia::where('playlist_item_id', $slide->id)
                            ->orderBy('id', 'desc')
                            ->take(count($rowsToInsert))
                            ->pluck('id')
                            ->toArray();

                        $keepSlotIds = array_merge($keepSlotIds, $insertedIds);
                    }
                }

                // 2) Remove slides/slots that are no longer present (no media deletion)
                if ($replaceMissing) {
                    // --- SLOTS ---
                    $allExistingSlotIds = ItemMedia::whereIn(
                        'playlist_item_id',
                        $playlist->playListItems()->pluck('id')
                    )->pluck('id')->toArray();

                    $toRemoveSlotIds = array_diff($allExistingSlotIds, $keepSlotIds);

                    // collect widgets linked to the slots that will be removed
                    $candidateWidgetIds = [];
                    if (!empty($toRemoveSlotIds)) {
                        $candidateWidgetIds = ItemMedia::whereIn('id', $toRemoveSlotIds)
                            ->whereNotNull('widget_id')
                            ->pluck('widget_id')
                            ->unique()
                            ->toArray();

                        // delete the slots
                        ItemMedia::whereIn('id', $toRemoveSlotIds)->delete();
                    }

                    // --- SLIDES ---
                    $allExistingSlideIds = $playlist->playListItems()->pluck('id')->toArray();
                    $toRemoveSlideIds = array_diff($allExistingSlideIds, $keepSlideIds);

                    if (!empty($toRemoveSlideIds)) {
                        // also collect widgets from slots under the slides being removed
                        $slideWidgetIds = ItemMedia::whereIn('playlist_item_id', $toRemoveSlideIds)
                            ->whereNotNull('widget_id')
                            ->pluck('widget_id')
                            ->unique()
                            ->toArray();

                        $candidateWidgetIds = array_values(array_unique(array_merge(
                            $candidateWidgetIds,
                            $slideWidgetIds
                        )));

                        // delete their slots then the slides
                        ItemMedia::whereIn('playlist_item_id', $toRemoveSlideIds)->delete();
                        PlaylistItem::whereIn('id', $toRemoveSlideIds)->delete();
                    }

                    // --- WIDGET CLEANUP (only orphaned) ---
                    if (!empty($candidateWidgetIds)) {
                        // which of the candidate widgets are still referenced by any remaining media item?
                        $stillUsed = ItemMedia::whereIn('widget_id', $candidateWidgetIds)
                            ->pluck('widget_id')
                            ->unique()
                            ->toArray();

                        $orphans = array_diff($candidateWidgetIds, $stillUsed);
                        if (!empty($orphans)) {
                            WidgetDetails::whereIn('id', $orphans)->delete();
                        }
                    }
                }

                // 3) Plan usage update (only additions)
                if ($userplan && $deltaAddMB > 0) {
                    $userplan->update(['used_storage' => $usedMB + $deltaAddMB]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Playlist updated successfully',
                'playlist_id' => $playlist->id,
            ], 200);
        } catch (\Throwable $e) {
            foreach ($createdFiles as $fullPath) {
                @unlink($fullPath);
            }
            Log::error('Playlist update failed: ' . get_class($e) . ' — ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Update failed. No changes were saved.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 422);
        }
    }
  
  
  
  
  
  
  
  
  
  
}
