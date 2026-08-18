<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evidence;
use App\Models\Gangguan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EvidenceController extends Controller
{
    public function upload(Request $request, Gangguan $gangguan): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'max:5120'],
        ]);

        $uploadedFile = $request->file('file');
        $storedFilename = Str::uuid()->toString() . '.' . $uploadedFile->getClientOriginalExtension();
        $storedPath = 'evidences/' . $storedFilename;

        $compressedBinary = $this->compressImage($uploadedFile->getRealPath(), $uploadedFile->getClientOriginalExtension());
        Storage::disk('public')->put($storedPath, $compressedBinary);

        $evidence = $gangguan->evidences()->create([
            'filename' => $request->file('file')->getClientOriginalName(),
            'filepath' => $storedPath,
        ]);

        return response()->json($evidence, 201);
    }

    public function destroy(Evidence $evidence): JsonResponse
    {
        if (Storage::disk('public')->exists($evidence->filepath)) {
            Storage::disk('public')->delete($evidence->filepath);
        }

        $evidence->delete();

        return response()->json([
            'message' => 'Evidence berhasil dihapus.',
        ]);
    }

    public function view(Evidence $evidence): BinaryFileResponse|JsonResponse|Response
    {
        if (!Storage::disk('public')->exists($evidence->filepath)) {
            return response()->json([
                'message' => 'File evidence tidak ditemukan.',
            ], 404);
        }

        return response()->file(Storage::disk('public')->path($evidence->filepath));
    }

    private function compressImage(string $sourcePath, string $extension): string
    {
        $extension = strtolower($extension);

        return match ($extension) {
            'jpg', 'jpeg' => $this->compressWithJpeg($sourcePath),
            'png' => $this->compressWithPng($sourcePath),
            'gif' => $this->compressWithGif($sourcePath),
            'webp' => $this->compressWithWebp($sourcePath),
            default => file_get_contents($sourcePath),
        };
    }

    private function compressWithJpeg(string $sourcePath): string
    {
        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagejpeg')) {
            return file_get_contents($sourcePath);
        }

        $image = imagecreatefromjpeg($sourcePath);
        ob_start();
        imagejpeg($image, null, 75);
        $binary = ob_get_clean();
        imagedestroy($image);

        return $binary !== false ? $binary : file_get_contents($sourcePath);
    }

    private function compressWithPng(string $sourcePath): string
    {
        if (!function_exists('imagecreatefrompng') || !function_exists('imagepng')) {
            return file_get_contents($sourcePath);
        }

        $image = imagecreatefrompng($sourcePath);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        imagepng($image, null, 6);
        $binary = ob_get_clean();
        imagedestroy($image);

        return $binary !== false ? $binary : file_get_contents($sourcePath);
    }

    private function compressWithGif(string $sourcePath): string
    {
        if (!function_exists('imagecreatefromgif') || !function_exists('imagegif')) {
            return file_get_contents($sourcePath);
        }

        $image = imagecreatefromgif($sourcePath);
        ob_start();
        imagegif($image);
        $binary = ob_get_clean();
        imagedestroy($image);

        return $binary !== false ? $binary : file_get_contents($sourcePath);
    }

    private function compressWithWebp(string $sourcePath): string
    {
        if (!function_exists('imagecreatefromwebp') || !function_exists('imagewebp')) {
            return file_get_contents($sourcePath);
        }

        $image = imagecreatefromwebp($sourcePath);
        ob_start();
        imagewebp($image, null, 80);
        $binary = ob_get_clean();
        imagedestroy($image);

        return $binary !== false ? $binary : file_get_contents($sourcePath);
    }
}
