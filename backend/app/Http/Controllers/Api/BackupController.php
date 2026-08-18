<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\WeeklyBackupJob;
use App\Models\Backup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Backup::latest('backup_date')->paginate(10));
    }

    public function download(Request $request): Response|JsonResponse|BinaryFileResponse
    {
        $payload = $request->validate([
            'backup_id' => ['required', 'exists:backups,id'],
        ]);

        $backup = Backup::findOrFail($payload['backup_id']);

        if (!Storage::disk('local')->exists($backup->path)) {
            return response()->json(['message' => 'File backup tidak ditemukan.'], 404);
        }

        return response()->download(
            Storage::disk('local')->path($backup->path),
            $backup->filename
        );
    }

    public function restore(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'backup_id' => ['required', 'exists:backups,id'],
        ]);

        $backup = Backup::findOrFail($payload['backup_id']);

        if (!Storage::disk('local')->exists($backup->path)) {
            return response()->json(['message' => 'File backup tidak ditemukan.'], 404);
        }

        // Placeholder restore routine. In production, run safe DB restore script with confirmation.
        return response()->json([
            'message' => 'Restore diproses (simulasi).',
            'backup' => $backup,
        ]);
    }

    public function trigger(): JsonResponse
    {
        WeeklyBackupJob::dispatch();

        return response()->json([
            'message' => 'Job backup mingguan berhasil dikirim ke queue.',
        ]);
    }
}
