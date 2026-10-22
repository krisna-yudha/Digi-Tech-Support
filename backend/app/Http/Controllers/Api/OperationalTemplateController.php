<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OperationalTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalTemplateController extends Controller
{
    /**
     * Display a listing of operational templates.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = OperationalTemplate::query()->with('user:id,name,email');

        // Setiap agent mempunyai template nya sendiri-sendiri (jangan dishare)
        if (!$user->hasRole('Admin')) {
            $query->where('user_id', $user->id);
        }

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
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'content' => 'required|string',
            'original_filename' => 'nullable|string|max:255',
        ]);

        $template = OperationalTemplate::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'category' => $validated['category'] ?? 'Umum',
            'content' => $validated['content'],
            'original_filename' => $validated['original_filename'] ?? null,
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

        if ($operationalTemplate->user_id !== $user->id && !$user->hasRole('Admin')) {
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
     * Update the specified operational template.
     */
    public function update(Request $request, OperationalTemplate $operationalTemplate): JsonResponse
    {
        $user = $request->user();

        $isOwner = $operationalTemplate->user_id === $user->id;
        $canEdit = $isOwner || $user->hasRole('Admin');

        if (!$canEdit) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak untuk mengubah template ini.',
            ], 403);
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

        $isOwner = $operationalTemplate->user_id === $user->id;
        $canDelete = $isOwner || $user->hasRole('Admin');

        if (!$canDelete) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak untuk menghapus template ini.',
            ], 403);
        }

        $operationalTemplate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template operasional berhasil dihapus.',
        ]);
    }
}
