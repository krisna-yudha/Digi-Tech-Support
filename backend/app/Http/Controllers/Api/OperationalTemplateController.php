<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OperationalTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OperationalTemplateController extends Controller
{
    /**
     * Display a listing of operational templates.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('Agent')) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur ini khusus untuk role Agent.',
            ], 403);
        }

        $query = OperationalTemplate::query()->with('user:id,name,email');

        // Setiap agent mempunyai template nya sendiri-sendiri (jangan dishare)
        $query->where('user_id', $user->id);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        $templates = $query->orderBy('updated_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Store a newly created operational template.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('Agent')) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur ini khusus untuk role Agent.',
            ], 403);
        }

        // Jika upload file langsung (misal PDF patokan baku yang tidak boleh diubah formatnya)
        if ($request->hasFile('file')) {
            $request->validate([
                'file' => 'required|file|max:30720', // maks 30MB
                'title' => 'nullable|string|max:255',
                'category' => 'nullable|string|max:100',
                'content' => 'nullable|string',
            ]);

            $uploadedFile = $request->file('file');
            $originalFilename = $uploadedFile->getClientOriginalName();
            $ext = strtolower($uploadedFile->getClientOriginalExtension());
            $filename = Str::uuid()->toString() . '.' . $ext;

            Storage::disk('public')->putFileAs('operational_templates', $uploadedFile, $filename);
            $storedPath = 'operational_templates/' . $filename;

            $title = $request->filled('title') ? $request->input('title') : pathinfo($originalFilename, PATHINFO_FILENAME);
            $content = $request->input('content') ?: ($title ?: 'Dokumen PDF Patokan');
            $category = $request->input('category') ?: ($ext === 'pdf' ? 'SOP' : 'Umum');

            $template = OperationalTemplate::create([
                'user_id' => $user->id,
                'title' => $title,
                'category' => $category,
                'content' => $content,
                'original_filename' => $originalFilename,
                'file_path' => $storedPath,
                'is_shared' => false,
            ]);

            $template->load('user:id,name,email');

            return response()->json([
                'success' => true,
                'message' => 'Dokumen patokan berhasil diunggah tanpa mengubah format asli.',
                'data' => $template,
            ], 201);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'content' => 'required|string',
            'original_filename' => 'nullable|string|max:255',
            'file_path' => 'nullable|string|max:255',
        ]);

        $template = OperationalTemplate::create([
            'user_id' => $user->id,
            'title' => $validated['title'],
            'category' => $validated['category'] ?? 'Umum',
            'content' => $validated['content'],
            'original_filename' => $validated['original_filename'] ?? null,
            'file_path' => $validated['file_path'] ?? null,
            'is_shared' => false,
        ]);

        $template->load('user:id,name,email');

        return response()->json([
            'success' => true,
            'message' => 'Template operasional berhasil disimpan.',
            'data' => $template,
        ], 201);
    }

    /**
     * Display the specified operational template.
     */
    public function show(Request $request, OperationalTemplate $operationalTemplate): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('Agent')) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur ini khusus untuk role Agent.',
            ], 403);
        }

        if ($operationalTemplate->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke template ini.',
            ], 403);
        }

        $operationalTemplate->load('user:id,name,email');

        return response()->json([
            'success' => true,
            'data' => $operationalTemplate,
        ]);
    }

    /**
     * Stream or download the original raw file (e.g. PDF benchmark).
     */
    public function file(Request $request, OperationalTemplate $operationalTemplate): BinaryFileResponse|JsonResponse|Response
    {
        $user = $request->user() ?: auth('sanctum')->user();
        if (!$user && $request->filled('token')) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($request->query('token'));
            if ($token) {
                $user = $token->tokenable;
            }
        }

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user->hasRole('Agent')) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur ini khusus untuk role Agent.',
            ], 403);
        }

        if ($operationalTemplate->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke dokumen ini.',
            ], 403);
        }

        if (!$operationalTemplate->file_path || !Storage::disk('public')->exists($operationalTemplate->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File dokumen tidak ditemukan di server.',
            ], 404);
        }

        $fullPath = Storage::disk('public')->path($operationalTemplate->file_path);
        $filename = $operationalTemplate->original_filename ?: 'dokumen.pdf';
        $mimeType = str_ends_with(strtolower($filename), '.pdf') ? 'application/pdf' : mime_content_type($fullPath);

        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Update the specified operational template.
     */
    public function update(Request $request, OperationalTemplate $operationalTemplate): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('Agent')) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur ini khusus untuk role Agent.',
            ], 403);
        }

        $isOwner = $operationalTemplate->user_id === $user->id;

        if (!$isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak untuk mengubah template ini.',
            ], 403);
        }

        // Dokumen patokan PDF bersifat baku dan tidak dapat diubah
        if (str_ends_with(strtolower($operationalTemplate->original_filename ?? ''), '.pdf')) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen patokan PDF bersifat baku dan tidak dapat diubah.',
            ], 422);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'content' => 'required|string',
            'original_filename' => 'nullable|string|max:255',
        ]);

        $operationalTemplate->update([
            'title' => $validated['title'],
            'category' => $validated['category'] ?? $operationalTemplate->category,
            'content' => $validated['content'],
            'original_filename' => $validated['original_filename'] ?? $operationalTemplate->original_filename,
            'is_shared' => false,
        ]);

        $operationalTemplate->load('user:id,name,email');

        return response()->json([
            'success' => true,
            'message' => 'Template operasional berhasil diperbarui.',
            'data' => $operationalTemplate,
        ]);
    }

    /**
     * Remove the specified operational template.
     */
    public function destroy(Request $request, OperationalTemplate $operationalTemplate): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('Agent')) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur ini khusus untuk role Agent.',
            ], 403);
        }

        $isOwner = $operationalTemplate->user_id === $user->id;

        if (!$isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak untuk menghapus template ini.',
            ], 403);
        }

        if ($operationalTemplate->file_path && Storage::disk('public')->exists($operationalTemplate->file_path)) {
            Storage::disk('public')->delete($operationalTemplate->file_path);
        }

        $operationalTemplate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template operasional berhasil dihapus.',
        ]);
    }
}
