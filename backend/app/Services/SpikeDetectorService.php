<?php

namespace App\Services;

use App\Models\Gangguan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SpikeDetectorService
{
    /**
     * Window threshold in minutes to detect spike.
     */
    const SPIKE_WINDOW_MINUTES = 5;

    /**
     * Minimum tickets count to trigger potential mass outage flag.
     */
    const SPIKE_COUNT_THRESHOLD = 3;

    /**
     * Evaluates a newly created ticket and triggers potential mass flag if spike is detected.
     */
    public function evaluate(Gangguan $gangguan): void
    {
        if ($gangguan->parent_id !== null || empty($gangguan->kategori)) {
            return;
        }

        $since = Carbon::now()->subMinutes(self::SPIKE_WINDOW_MINUTES);

        $recentTickets = Gangguan::where('kategori', $gangguan->kategori)
            ->whereNull('parent_id')
            ->whereIn('status', ['open', 'in_progress'])
            ->where('created_at', '>=', $since)
            ->get();

        if ($recentTickets->count() >= self::SPIKE_COUNT_THRESHOLD) {
            Gangguan::whereIn('id', $recentTickets->pluck('id'))
                ->update(['is_potential_massal' => true]);
        }
    }

    /**
     * Returns list of active spike alerts for TS/Admin dashboard.
     */
    public function getActiveSpikeAlerts(): array
    {
        // Look back 20 minutes for active spike candidates that have not been merged
        $since = Carbon::now()->subMinutes(20);

        $candidates = Gangguan::with(['creator:id,name'])
            ->whereNull('parent_id')
            ->where('jenis_gangguan', '!=', 'Massal')
            ->whereIn('status', ['open', 'in_progress'])
            ->where('created_at', '>=', $since)
            ->where(function ($q) {
                $q->where('is_potential_massal', true)
                  ->orWhereRaw('(SELECT COUNT(*) FROM gangguans g2 WHERE g2.kategori = gangguans.kategori AND g2.parent_id IS NULL AND g2.status IN ("open", "in_progress") AND g2.created_at >= ?) >= ?', [
                      Carbon::now()->subMinutes(self::SPIKE_WINDOW_MINUTES),
                      self::SPIKE_COUNT_THRESHOLD
                  ]);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $grouped = $candidates->groupBy('kategori');

        $alerts = [];
        foreach ($grouped as $kategori => $tickets) {
            if ($tickets->count() >= 2) { // show alert if at least 2 tickets in category
                $alerts[] = [
                    'kategori'          => $kategori,
                    'count'             => $tickets->count(),
                    'ticket_ids'        => $tickets->pluck('id')->values()->all(),
                    'tickets'           => $tickets->map(fn($t) => [
                        'id'            => $t->id,
                        'ticket_number' => $t->ticket_number,
                        'judul'         => $t->judul,
                        'creator_name'  => $t->creator?->name ?? 'Agent',
                        'created_at'    => $t->created_at?->toISOString(),
                        'start_time'    => $t->start_time?->toISOString(),
                    ])->values()->all(),
                    'first_reported_at' => $tickets->min('created_at')?->toISOString(),
                    'latest_reported_at'=> $tickets->max('created_at')?->toISOString(),
                ];
            }
        }

        return $alerts;
    }

    /**
     * Returns list of currently active mass or potential mass incidents.
     */
    public function getActiveIncidents(?string $kategori = null, ?int $userId = null): Collection
    {
        $since = Carbon::now()->subHours(6); // Incidents in the last 6 hours that are still active

        $query = Gangguan::with(['creator:id,name', 'assignee:id,name', 'affectedAgents.user:id,name'])
            ->whereNull('parent_id')
            ->whereIn('status', ['open', 'in_progress'])
            ->where(function ($q) use ($since) {
                $q->where('jenis_gangguan', 'Massal')
                  ->orWhere('is_potential_massal', true)
                  ->orWhere('jumlah_agent_terdampak', '>', 1)
                  ->orWhere('created_at', '>=', $since);
            })
            ->orderBy('created_at', 'desc');

        if ($kategori) {
            $query->where(function ($q) use ($kategori) {
                $q->where('kategori', $kategori)
                  ->orWhere('jenis_gangguan', 'Massal');
            });
        }

        $incidents = $query->get();

        return $incidents->map(function ($incident) use ($userId) {
            $hasJoined = false;
            if ($userId) {
                $hasJoined = ($incident->created_by === $userId) ||
                    $incident->affectedAgents->contains('user_id', $userId);
            }

            return [
                'id'                     => $incident->id,
                'ticket_number'          => $incident->ticket_number,
                'judul'                  => $incident->judul,
                'kategori'               => $incident->kategori,
                'jenis_gangguan'         => $incident->jenis_gangguan,
                'is_potential_massal'    => (bool) $incident->is_potential_massal,
                'status'                 => $incident->status,
                'priority'               => $incident->priority,
                'jumlah_agent_terdampak' => $incident->jumlah_agent_terdampak ?? ($incident->affectedAgents->count() + 1),
                'created_at'             => $incident->created_at?->toISOString(),
                'start_time'             => $incident->start_time?->toISOString(),
                'has_joined'             => $hasJoined,
                'assignee_name'          => $incident->assignee?->name ?? 'Belum Ditugaskan',
                'affected_agents_count'  => $incident->affectedAgents->count(),
            ];
        });
    }
}
