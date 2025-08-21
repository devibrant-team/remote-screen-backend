<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Ads;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class AdsController extends Controller
{
    public function index(){
        $ads=Ads::all();
        return response()->json(["ads" => $ads]);

    }


    public function store(Request $request){
        $request->validate([
            'media'=>'required|file',
            'media_type'=>'required',
            'description' => 'required',
        ]);
        
       $user = auth()->user();

        if (!$user || !$user->tokenCan('Admin')) {
            return response()->json(['error' => 'Unauthorized1234'], 401);
        }
        
        $imageUrl='';

        if ($request->hasFile("media")) {
   
                    $file = $request->file("media");

                    $imageName = Str::random(20) . '.' . $file->getClientOriginalExtension();
                    $path = public_path("image/ads");

                    if (!File::exists($path)) {
                        if (!File::makeDirectory($path, 0777, true)) {
                            throw new \RuntimeException('Failed to create destination directory.');
                        }
                    }

        
                    if (!$file->move($path, $imageName)) {
                        throw new \RuntimeException('Failed to move uploaded file.');
                    }

                    $relativePath = "image/ads/$imageName";
                    $absolutePath = $path . '/' . $imageName;
                    $movedFiles[] = $absolutePath; // track for potential cleanup

                    $imageUrl  = asset($relativePath);
                   
                }



        $ads=Ads::create([
            'media'=>$imageUrl,
            'media_type'=>$request->media_type,
            'description' => $request->description,
        ]);

        return response()->json(["ads" => $ads]);

    }



    public function update(Request $request,$id){
        $request->validate([
            'name'=>'required',
            'image'=>'nullable|file'
        ]);

        $user = auth()->user();

        if (!$user || !$user->tokenCan('Admin')) {
            return response()->json(['error' => 'Unauthorized1234'], 401);
        }
        $ads=Ads::findOrFail($id);

        if ($request->hasFile("image")) {
    $file = $request->file("image");

    $imageName = Str::random(20) . '.' . $file->getClientOriginalExtension();
    $path = public_path("image/ads");

    // Create folder if not exists
    if (!File::exists($path)) {
        if (!File::makeDirectory($path, 0777, true)) {
            throw new \RuntimeException('Failed to create destination directory.');
        }
    }

    // ===== Delete old image =====
    if (!empty($yourModel->image)) { // assuming "image" stores the relative path
        $oldImagePath = public_path($ads->image);
        if (File::exists($oldImagePath)) {
            File::delete($oldImagePath);
        }
    }

    // ===== Upload new image =====
    if (!$file->move($path, $imageName)) {
        throw new \RuntimeException('Failed to move uploaded file.');
    }

    $relativePath = "image/ads/$imageName";
    $absolutePath = $path . '/' . $imageName;
    $movedFiles[] = $absolutePath; // track for potential cleanup

    // Keep same URL format as before
    $imageUrl = asset($relativePath);

}

    $ads->update([
        'name'=>$request->name,
        'image'=>$imageUrl,

    ]);
        return response()->json(["ads" => $ads]);

    }

public function destroy($id)
{

    $user = auth()->user();

        if (!$user || !$user->tokenCan('Admin')) {
            return response()->json(['error' => 'Unauthorized1234'], 401);
        }

    $ad = Ads::findOrFail($id);

    // Delete image file if exists
    if (!empty($ad->image)) { // assuming 'image' stores relative path like "image/ads/file.jpg"
        $imagePath = public_path($ad->image);
        if (File::exists($imagePath)) {
            File::delete($imagePath);
        }
    }

    // Delete DB record
    $ad->delete();

    return response()->json([
        'success' => true,
        'message' => 'Ad deleted successfully.'
    ]);
}




}
