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

class PlayListController extends Controller
{

    public function storeNormal(Request $request)
{
    $request->validate([
        'name' => 'required|string',
        'style_id' => 'required|integer',
        'slides_number' => 'required|integer',
        'total_duration' => 'required|integer',
        'slides' => 'required|array',
    ]);

    $user = auth()->user();

    $playlist = Playlist::create([
        'name' => $request->name,
        'user_id' => $user->id,
        'style_id' => $request->style_id,
        'duration' => $request->total_duration,
        'slide_number' => $request->slides_number,
    ]);

    foreach ($request->slides as $slideIndex => $slideData) {
        $slide = PlaylistItem::create([
            'playlist_id' => $playlist->id,
            'duration' => $slideData['duration'],
            'grid_style' => $slideData['grid_style'],
            'transition' => $slideData['transition'],
            'index' => $slideData['index'],
        ]);

        foreach ($slideData['list_media'] as $mediaIndex => $media) {
            $media_id = $media['media_id'] ?? null;

            // Create new media if no media_id and media_url exists (as a file input name)
            if (!$media_id && $request->hasFile("slides.$slideIndex.list_media.$mediaIndex.media_url")) {
                $file = $request->file("slides.$slideIndex.list_media.$mediaIndex.media_url");

                $imageName = Str::random(20) . '.' . $file->getClientOriginalExtension();
                $path = public_path("image/{$user->name}/{$request->name}");

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0777, true);
                }

                $file->move($path, $imageName);

                $relativePath = "image/{$user->name}/{$request->name}/$imageName";
                $imageUrl = asset($relativePath);
                $imageSize = round(filesize($path . '/' . $imageName) / 1024, 2); // KB

                $createdMedia = Media::create([
                    'type' => $media['media_type'],
                    'user_id' => $user->id,
                    'widget_id' => $media['widget'],
                    'media' => $imageUrl,
                    'storage' => $imageSize,
                ]);

                $media_id = $createdMedia->id;
            }

            MediaItem::create([
                'slide_id' => $slide->id,
                'scale' => $media['scale'],
                'index' => $media['index'],
                'media_id' => $media_id,
            ]);
        }
    }

    return response()->json(['message' => 'Playlist created successfully']);
}




  public function storeInteractive(Request $request)
{
    $request->validate([
        'name' => 'required|string',
        'style_id' => 'required|integer',
        'slides_number' => 'required|integer',
        'total_duration' => 'required|integer',
        'slides' => 'required|array',
    ]);

    $user = auth()->user();

    $playlist = Playlist::create([
        'name' => $request->name,
        'user_id' => $user->id,
        'style_id' => $request->style_id,
        'duration' => $request->total_duration,
        'slide_number' => $request->slides_number,
    ]);

    foreach ($request->slides as $slideIndex => $slideData) {
        $slide = PlaylistItem::create([
            'playlist_id' => $playlist->id,
            'duration' => $slideData['duration'],
            'grid_style' => $slideData['grid_style'],
            'transition' => $slideData['transition'],
            'index' => $slideData['index'],
        ]);

        foreach ($slideData['list_media'] as $mediaIndex => $media) {
            $media_id = $media['media_id'] ?? null;

            // Create new media if no media_id and media_url exists (as a file input name)
            if (!$media_id && $request->hasFile("slides.$slideIndex.list_media.$mediaIndex.media_url")) {
                $file = $request->file("slides.$slideIndex.list_media.$mediaIndex.media_url");

                $imageName = Str::random(20) . '.' . $file->getClientOriginalExtension();
                $path = public_path("image/{$user->name}/{$request->name}");

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0777, true);
                }

                $file->move($path, $imageName);

                $relativePath = "image/{$user->name}/{$request->name}/$imageName";
                $imageUrl = asset($relativePath);
                $imageSize = round(filesize($path . '/' . $imageName) / 1024, 2); // KB

                $createdMedia = Media::create([
                    'type' => $media['media_type'],
                    'user_id' => $user->id,
                    'widget_id' => $media['widget'],
                    'media' => $imageUrl,
                    'storage' => $imageSize,
                ]);

                $media_id = $createdMedia->id;
            }

            MediaItem::create([
                'slide_id' => $slide->id,
                'scale' => $media['scale'],
                'index' => $media['index'],
                'media_id' => $media_id,
            ]);
        }
    }

    return response()->json(['message' => 'Playlist created successfully']);
}




}
