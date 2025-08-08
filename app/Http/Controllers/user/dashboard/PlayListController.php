<?php

namespace App\Http\Controllers\User\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\MediaItem;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
class PlayListController extends Controller
{

public function index(Request $request)
{
    // $user = auth()->user();

    // if (!$user || !$request->user()->tokenCan('user_dashboard')) {
    //     return response()->json(['error' => 'Unauthorized'], 401);
    // }

    $playlists = Playlist::with([
        'planListStyle:id,type',
        'playListItems.playListItemStyle:id,type',
        'playListItems.itemMedia.media:id,type,media'
    ])->where('user_id', 1)->get();

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
    ])->findOrFail($id); // Get the selected playlist by ID

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
        'NumberOfSlides' => 'required|integer',
        'total_duration' => 'required|integer',
        'slides' => 'required|array',
    ]);
Log::info($request);
    // $user = auth()->user();

    $playlist = Playlist::create([
        'name' => $request->name,
        'user_id' => 1,
        'style_id' => $request->type,
        'duration' => $request->total_duration,
        'slide_number' => $request->NumberOfSlides,
    ]);

    foreach ($request->slides as $slideIndex => $slideData) {
    $slide = PlaylistItem::create([
        'playlist_id' => $playlist->id,
        'duration' => $slideData['duration'],
        'grid_id' => $slideData['grid_style'],
        'transition' => 'fade',
        'index' => $slideData['index'],
    ]);

    foreach ($slideData['slots'] as $slotIndex => $slot) {
        $media_id = $slot['media_id'] ?? null;
        $media_id = $media_id === 'null' ? null : $media_id;

        // Check if there's an uploaded file for this slot
        if (!$media_id && $request->hasFile("slides.$slideIndex.slots.$slotIndex.media")) {
            $file = $request->file("slides.$slideIndex.slots.$slotIndex.media");

            $imageName = Str::random(20) . '.' . $file->getClientOriginalExtension();
            $path = public_path("image/omar/{$request->name}");

            if (!File::exists($path)) {
                File::makeDirectory($path, 0777, true);
            }

            $file->move($path, $imageName);

            $relativePath = "image/omar/{$request->name}/$imageName";
            $imageUrl = asset($relativePath);
            $imageSize = round(filesize($path . '/' . $imageName) / 1024, 2); // KB

            $createdMedia = Media::create([
                'type' => $slot['mediaType'], // <-- changed to $slot
                'user_id' => 1,
                'widget_id' => 1,
                'media' => $imageUrl,
                'storage' => $imageSize,
            ]);

            $media_id = $createdMedia->id;
        }

        // Save media item
        MediaItem::create([
            'playlist_item_id' => $slide->id,
            'scale' => $slot['scale'],
            'index' => $slot['index'],
            'media_id' => $media_id,
        ]);
    }
}


    return response()->json(['message' => 'Playlist created successfully']);
}




  public function storeInteractive(Request $request)
{
    // return $request;
    $request->validate([
        'name' => 'required|string',
        'style_id' => 'required|integer',
        'slide_number' => 'required|integer',
        'slides' => 'required|array',
    ]);

    $user = auth()->user();

    $playlist = Playlist::create([
        'name' => $request->name,
        'user_id' => 1,
        'style_id' => $request->style_id,
        'duration' => 0,
        'slide_number' => $request->slide_number,
    ]);
    // return $playlist->id;
    foreach ($request->slides as $slideIndex => $slideData) {
        $slide = PlaylistItem::create([
            'playlist_id' => $playlist->id,
            'duration' => 0,
            'index' => $slideData['index'],
        ]);

  
     $media_id = ($slideData['media_id'] ?? null);
$media_id = $media_id === 'null' ? null : $media_id;

            // Create new media if no media_id and media_url exists (as a file input name)
            if (!$media_id && $request->hasFile("slides.$slideIndex.media")) {
                $file =  $request->file("slides.$slideIndex.media");
               
                $imageName = Str::random(20) . '.' . $file->getClientOriginalExtension();
                $path = public_path("image/omar/{$request->name}");

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0777, true);
                }

                $file->move($path, $imageName);

                $relativePath = "image/omar/{$request->name}/$imageName";
                $imageUrl = asset($relativePath);
                $imageSize = round(filesize($path . '/' . $imageName) / 1024, 2); // KB

                $createdMedia = Media::create([
                    'type' => 'image',
                    'user_id' => 1,
                    'media' => $imageUrl,
                    'storage' => $imageSize,
                    'widget_id'=>1,
                ]);

                $media_id = $createdMedia->id;
            }
            // return 'return'.$media_id;
             MediaItem::create([
                'playlist_item_id' => $slide->id,
                'index' => 0,
                'media_id' => $media_id,
            ]);



        
    }

    return response()->json(['message' => 'Playlist created successfully']);
}



public function getMedia(Request $request){
    $user = auth()->user();

    // if (!$user || !$request->user()->tokenCan('user_dashboard')) {
    //     return response()->json(['error' => 'Unauthorized'], 401);
    // }
    $media = Media::where('user_id',1)->get();
    return response()->json(['success' =>true,'media'=>$media]);


}




}
