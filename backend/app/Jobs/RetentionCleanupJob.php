<?php

namespace App\Jobs;

use App\Models\Gangguan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetentionCleanupJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Gangguan::query()
            ->where('created_at', '<', now()->subDays(30))
            ->each(function (Gangguan $gangguan) {
                $gangguan->delete();
            });
    }
}
