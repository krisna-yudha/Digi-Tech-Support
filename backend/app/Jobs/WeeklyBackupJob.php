<?php

namespace App\Jobs;

use App\Models\Backup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class WeeklyBackupJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $timestamp = now()->format('Ymd_His');
        $filename = "backup_{$timestamp}.sql";
        $path = "backups/{$filename}";

        // Placeholder dump content; replace with real mysqldump/pg_dump orchestration.
        Storage::disk('local')->put($path, '-- database dump placeholder --');

        Backup::create([
            'filename' => $filename,
            'path' => $path,
            'backup_date' => now(),
            'status' => 'success',
        ]);
    }
}
