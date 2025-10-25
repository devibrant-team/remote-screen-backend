<?php

namespace App\Http\Controllers\User\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Media;
use App\Models\ImageCapture;
use App\Models\UserPlan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class MediaController extends Controller
{
       public function getMedia(Request $request)
    {
        $user = auth()->user();

        if (!$user || !$request->user()->tokenCan('user_dashboard')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $media = Media::where('user_id', $user->id)->paginate(24);

        $formattedPlaylists = $media->map(function ($playlist) {
            return [
                'id' => $playlist->id,
                'type' => $playlist->type,
                'media' => $playlist->media,
              	'storage' => $playlist->storage,


            ];
        });
        return response()->json(['success' => true, 'media' => $formattedPlaylists]);
    }
  
  
  
 
  
  
  public function store(Request $request)
{
    // 1) Validate nested files: media[].file
    $request->validate([
        'media'           => 'required|array|min:1',
        'media.*.file'    => 'required|file|mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi|max:20480',
    ], [
        'media.required'        => 'Please attach at least one file.',
        'media.array'           => 'Media must be an array.',
        'media.*.file.required' => 'Each media item must include a file field.',
        'media.*.file.file'     => 'Each media item must be an uploaded file.',
        'media.*.file.mimes'    => 'Only images/videos are allowed (jpeg,png,jpg,gif,webp,mp4,mov,avi).',
        'media.*.file.max'      => 'Each file must be <= 20MB.',
    ]);

    // 2) Auth + scope
    $user = auth()->user();
    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $createdFiles = [];
    try {
        $result = DB::transaction(function () use ($request, $user, &$createdFiles) {
            // 3) Lock plan row
            $userplan = UserPlan::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$userplan) {
                return [
                    'ok' => false,
                    'resp' => response()->json(['error' => 'You do not have a plan'], 403),
                ];
            }

            $usedMB  = (float) ($userplan->used_storage ?? 0);
            $limitMB = (float) ($userplan->storage ?? 0);
            $deltaMB = 0.0;

            $safeUser = \Illuminate\Support\Str::slug($user->name);
            $mediaRecords = [];

            // 🔹 Retrieve nested files: each item has ['file' => UploadedFile]
            $items = $request->file('media'); // array of arrays
            foreach ($items as $idx => $item) {
                /** @var \Illuminate\Http\UploadedFile|null $file */
                $file = $item['file'] ?? null;
                if (!$file) {
                    return [
                        'ok' => false,
                        'resp' => response()->json(['error' => "Missing file at media[$idx].file"], 422),
                    ];
                }

                // preliminary quota check
                $fileSizeMB = round($file->getSize() / 1024 / 1024, 2);
                if ($usedMB + $deltaMB + $fileSizeMB > $limitMB) {
                    return [
                        'ok' => false,
                        'resp' => response()->json(['error' => 'You have reached your storage limit'], 422),
                    ];
                }

                $imageName  = \Illuminate\Support\Str::random(20) . '.' . $file->getClientOriginalExtension();
                $storedPath = $file->storeAs("media/{$safeUser}-{$user->id}/library", $imageName, 'public');
                if (!$storedPath) {
                    return [
                        'ok' => false,
                        'resp' => response()->json(['error' => 'Failed to store uploaded file.'], 422),
                    ];
                }

                $fullPath = storage_path("app/public/{$storedPath}");
                $createdFiles[] = $fullPath;

                // actual on-disk size
                $actualSizeMB = round(filesize($fullPath) / 1024 / 1024, 3);
                $deltaMB += $actualSizeMB;

                $fullUrl = asset('storage/' . $storedPath);
                $mime    = $file->getMimeType();
                $mtype   = str_starts_with((string)$mime, 'video/') ? 'video' : 'image';

                $media = Media::create([
                    'type'    => $mtype,
                    'user_id' => $user->id,
                    'media'   => $fullUrl,
                    'storage' => $actualSizeMB,
                ]);

                $mediaRecords[] = $media;
            }

            // 6) Update plan only once
            $userplan->update([
                'used_storage' => $usedMB + $deltaMB,
            ]);

            return [
                'ok' => true,
                'media' => $mediaRecords,
                'used_storage' => $usedMB + $deltaMB,
            ];
        }, 3);

        if (!$result['ok']) {
            return $result['resp'];
        }

        // shape response as array of objects
        $out = array_map(function ($m) {
            return [
                'id'   => $m->id,
                'url'  => $m->media,
                'type' => $m->type,
                'size' => $m->storage,
            ];
        }, $result['media']);

        return response()->json([
            'success'       => true,
            'message'       => 'Media created successfully',
            'media'         => $out,
            'used_storage'  => $result['used_storage'],
        ], 201);

    } catch (\Throwable $e) {
        foreach ($createdFiles as $fullPath) {
            @unlink($fullPath);
        }
        \Log::error('Media upload failed: '.get_class($e).' — '.$e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to create media.',
            'error'   => config('app.debug') ? $e->getMessage() : null,
        ], 422);
    }
}
  
  
  
  
  
  
public function imageupload(Request $request)
{
    $data = $request->validate([
        'media' => 'required|file|mimes:jpeg,png,jpg,gif,webp|max:20480', // 20MB
    ]);

    $file = $data['media'];
    $fullPath = null;

    try {
        // ✅ Store the original file locally
        $binary = file_get_contents($file->getRealPath());
$base64 = base64_encode($binary);
$mime = $file->getMimeType() ?: 'image/jpeg'; // fallback
$dataUrl = "base64,{$base64}";

        // ✅ DeepImage config
        $config = [
            "enhancements" => [
                "denoise",
                "deblur",
                "light",
                "color",
                "white_balance",
                "exposure_correction",
                "face_enhance",
            ],
            "light_parameters" => ["type" => "hdr_light_advanced", "level" => 1],
            "color_parameters" => ["type" => "contrast", "level" => 0.85],
            "white_balance_parameters" => ["type" => "v2", "level" => 0.5],
            "deblur_parameters" => ["type" => "v2"],
            "denoise_parameters" => ["type" => "v2"],
            "generative_upscale" => true,
            "upscale_strength" => 1,
            "background" => [
                "generate" => ["adapter_type" => "upscale", "controlnet_conditioning_scale" => 0.7]
            ],
            "face_enhance_parameters" => [
                "faceEnhancePanel" => true,
                "type" => "beautify-real",
                "level" => 0,
                "smoothing_level" => 0.2,
            ],
            "url" => $dataUrl,
            "width" => 2000,
        ];

        // ✅ Send to Deep-Image
        $response = Http::withHeaders([
            "x-api-key" => env("DEEPIMAGE_API_KEY"),
            "Content-Type" => "application/json",
        ])->post("https://deep-image.ai/rest_api/process_result", $config);

        if (!$response->ok()) {
            return response()->json([
                "success" => false,
                "error" => "Deep-Image request failed",
                "details" => $response->body(),
            ], $response->status());
        }

        $data = $response->json();

        // ✅ If result is not ready, poll until complete
        $jobId = $data["job"] ?? null;
        $resultUrl = $data["result_url"] ?? null;

        if (!$resultUrl && $jobId) {
            $maxAttempts = 20; // ~100 seconds total
            $delay = 5;
            for ($i = 0; $i < $maxAttempts; $i++) {
                sleep($delay);
                $check = Http::withHeaders([
                    "x-api-key" => env("DEEPIMAGE_API_KEY"),
                ])->get("https://deep-image.ai/rest_api/result/{$jobId}");

                if ($check->ok()) {
                    $checkData = $check->json();
                    if (($checkData["status"] ?? null) === "complete" && isset($checkData["result_url"])) {
                        $resultUrl = $checkData["result_url"];
                        break;
                    }
                }
            }
        }

        if ($resultUrl) {
            // ✅ Download the enhanced image into your backend storage
            $enhancedContent = Http::get($resultUrl)->body();

            $enhancedName = 'enhanced_' . Str::random(10) . '.jpg';
            $enhancedPath = 'media/upload-image/enhanced/' . $enhancedName;

            Storage::disk('public')->put($enhancedPath, $enhancedContent);

            $enhancedUrl = asset('storage/' . $enhancedPath);

            // ✅ Save in DB
            $media = ImageCapture::create([
                'url' => $enhancedUrl,
            ]);

            return response()->json([
                "success" => true,
                "message" => "Image enhanced and saved successfully",
                "media" => $media->url,
            ], 201);
        }

        return response()->json([
            "success" => false,
            "message" => "Deep-Image job did not finish in time.",
            "job" => $jobId,
        ], 202);

    } catch (\Throwable $e) {
        
        return response()->json([
            "success" => false,
            "message" => "Failed to create media.",
            "error" => config("app.debug") ? $e->getMessage() : null,
        ], 422);
    }
}
  
  
  
  
  
  
public function destroy(Request $request, $id)
{
    $user = auth()->user();
    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
 

    $media = Media::find($id);
    if (!$media) {
        return response()->json(['error' => 'Media not found'], 404);
    }
   if($user->id != $media->user_id){
   return response()->json(['error' => 'An error occurred,Please Try again'], 404);
   }

    try {
        $defaultUrl = "https://srv964353.hstgr.cloud/storage/default.png";

        return DB::transaction(function () use ($media, $user, $defaultUrl) {
            // 🔹 Lock user plan
            $userplan = UserPlan::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$userplan) {
                return response()->json(['error' => 'You do not have a plan'], 403);
            }

            // 🔹 Get how much storage this media used
            $freedMB = (float) ($media->storage ?? 0);

            // 🔹 Delete file if exists
            $currentUrl = $media->media;
            $pathFromUrl = ltrim(parse_url($currentUrl, PHP_URL_PATH) ?? '', '/');
            if (str_starts_with($pathFromUrl, 'storage/')) {
                $pathFromUrl = substr($pathFromUrl, strlen('storage/'));
            }
            if ($pathFromUrl && Storage::disk('public')->exists($pathFromUrl)) {
                Storage::disk('public')->delete($pathFromUrl);
            }

            // 🔹 Update media record to placeholder
            $media->update([
                'type'    => 'image',
                'media'   => $defaultUrl,
                'user_id' => 1,         // ✅ keep your original logic
                'storage' => 0.0,
            ]);

            // 🔹 Update userplan storage
            $usedNow = (float) ($userplan->used_storage ?? 0.0);
            $newUsed = max(0.0, round($usedNow - $freedMB, 3));
            $userplan->update(['used_storage' => $newUsed]);

            return response()->json([
                'success'      => true,
                'message'      => 'Media is deleted and storage updated',
                'freed_mb'     => $freedMB,
                'used_storage' => $newUsed,
            ], 200);
        });

    } catch (\Throwable $e) {
        \Log::error("Failed to update media ID {$id}: " . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to update media',
            'error'   => config('app.debug') ? $e->getMessage() : null,
        ], 422);
    }
}
  
  
  
  
  
  
  
}
