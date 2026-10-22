<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gangguan;
use App\Models\GangguanAffectedAgent;
use App\Services\SpikeDetectorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GangguanController extends Controller
{
    public function index(): JsonResponse
    {
        $this->requireRoles(request(), ['Admin', 'TS']);

        $allowed  = ['created_at', 'start_time', 'end_time', 'durasi', 'kategori', 'status', 'priority', 'jenis_gangguan'];
        $sortBy   = in_array(request('sort_by'), $allowed) ? request('sort_by') : 'created_at';
        $sortDir  = request('sort_dir') === 'asc' ? 'asc' : 'desc';
        $perPage  = min((int) request('per_page', 10), 100);

        $query = Gangguan::with([
            'creator:id,name',
            'assignee:id,name',
            'parent:id,ticket_number,judul,status',
            'children:id,ticket_number,judul,status,created_by,created_at',
            'children.creator:id,name',
            'affectedAgents:id,gangguan_id,user_id,agent_name,cubicle_name'
        ])->orderBy($sortBy, $sortDir);

        if (request()->filled('status')) {
            $query->where('status', request('status'));
        }
        if (request()->filled('jenis_gangguan')) {
            $query->where('jenis_gangguan', request('jenis_gangguan'));
        }
        if (request()->filled('kategori')) {
            $query->where('kategori', request('kategori'));
        }
        if (request()->filled('search')) {
            $q = '%' . request('search') . '%';
            $query->where(function ($w) use ($q) {
                $w->where('judul', 'like', $q)
                  ->orWhere('ticket_number', 'like', $q)
                  ->orWhere('id_task_sip', 'like', $q)
                  ->orWhere('kategori', 'like', $q)
                  ->orWhere('penyebab_permasalahan', 'like', $q)
                  ->orWhere('penyelesaian_masalah', 'like', $q)
                  ->orWhereHas('creator', fn($r) => $r->where('name', 'like', $q));
            });
        }

        if (request()->filled('period')) {
            $period = request('period');
            if ($period === 'today') {
                $query->whereDate('created_at', Carbon::today());
            } elseif ($period === 'this_week') {
                $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            } elseif ($period === 'this_month') {
                $query->whereMonth('created_at', Carbon::now()->month)
                      ->whereYear('created_at', Carbon::now()->year);
            } elseif ($period === 'custom') {
                if (request()->filled('start_date')) {
                    $query->whereDate('created_at', '>=', request('start_date'));
                }
                if (request()->filled('end_date')) {
                    $query->whereDate('created_at', '<=', request('end_date'));
                }
            }
        }

        $data = $query->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requireRoles($request, ['Agent', 'TS', 'Admin']);

        $payload = $request->validate([
            'cubicle'        => ['required', 'string', 'max:100'],
            'agent_name'     => ['required', 'string', 'max:255'],
            'problem'        => ['required', 'string'],
            'judul'          => ['nullable', 'string', 'max:255'],
            'deskripsi'      => ['nullable', 'string'],
            'status'         => ['nullable', 'string', 'max:50'],
            'jenis_gangguan' => ['nullable', 'string', 'in:Personal,Massal'],
            'kategori'       => ['nullable', 'string', 'max:100'],
            'priority'       => ['nullable', 'in:low,medium,high'],
            'assigned_to'    => ['nullable', 'exists:users,id'],
            'start_time'     => ['nullable', 'date'],
            'end_time'       => ['nullable', 'date', 'after_or_equal:start_time'],
        ]);

        $cubicle   = $payload['cubicle'];
        $agentName = $payload['agent_name'];
        $problem   = $payload['problem'];

        $payload['judul']          = $payload['judul'] ?? "Gangguan {$cubicle} - {$agentName}";
        $payload['deskripsi']      = $payload['deskripsi'] ?? "Cubicle: {$cubicle}\nAgent: {$agentName}\nProblem: {$problem}";
        $payload['kategori']       = $payload['kategori'] ?? $cubicle;
        $payload['ticket_number']  = $this->generateTicketNumber();
        $payload['created_by']     = $request->user()->id;
        $payload['status']         = $payload['status'] ?? 'open';
        $payload['jenis_gangguan'] = $payload['jenis_gangguan'] ?? 'Personal';
        $payload['priority']       = $payload['priority'] ?? 'medium';
        $payload['start_time']     = $payload['start_time'] ?? now();
        $payload['durasi']         = $this->calculateDuration($payload['start_time'] ?? null, $payload['end_time'] ?? null);

        unset($payload['cubicle'], $payload['agent_name'], $payload['problem']);

        $gangguan = Gangguan::create($payload);

        // Evaluate Spike / Anomaly Detection for Mass Outage
        app(SpikeDetectorService::class)->evaluate($gangguan);

        return response()->json($gangguan, 201);
    }

    public function show(Request $request, Gangguan $gangguan): JsonResponse
    {
        $this->requireRoles($request, ['Admin', 'TS']);

        // Auto-stamp: catat waktu pertama kali TS membaca tiket ini
        if (is_null($gangguan->read_at)) {
            $gangguan->update([
                'read_at' => now(),
                'read_by' => $request->user()->id,
            ]);
        }

        $gangguan->load([
            'creator:id,name',
            'assignee:id,name,signature',
            'reader:id,name',
            'resolver:id,name',
            'evidences',
            'parent:id,ticket_number,judul,status,kategori',
            'children:id,ticket_number,judul,status,created_by,created_at,kategori',
            'children.creator:id,name',
            'affectedAgents.user:id,name'
        ]);

        $cubicleName = '';
        if ($gangguan->deskripsi && preg_match('/Cubicle:\s*([^\n]+)/i', $gangguan->deskripsi, $matches)) {
            $cubicleName = trim($matches[1]);
        }
        $gangguan->cubicle_name = $cubicleName;

        if ($cubicleName) {
            $cubicleData = \Illuminate\Support\Facades\DB::table('cubicles')
                ->where('nama', $cubicleName)
                ->first();
            if ($cubicleData) {
                $gangguan->cubicle_ext = $cubicleData->ext;
                $gangguan->cubicle_ip = $cubicleData->ip;
                $gangguan->cubicle_rochet = $cubicleData->rochet;
            }
        }
        return response()->json($gangguan);
    }

    public function showForAgent(Request $request, Gangguan $gangguan): JsonResponse
    {
        $this->requireRoles($request, ['Agent']);

        // Pastikan laporan ini milik agent yang sedang login atau agent tercatat sebagai affected agent
        $isCreator = $gangguan->created_by === $request->user()->id;
        $isAffected = $gangguan->affectedAgents()->where('user_id', $request->user()->id)->exists();

        abort_unless(
            $isCreator || $isAffected,
            403,
            'Anda tidak memiliki akses ke laporan ini.'
        );

        $gangguan->load(['evidences', 'parent:id,ticket_number,judul,status', 'affectedAgents.user:id,name']);

        return response()->json($gangguan);
    }

    public function update(Request $request, Gangguan $gangguan): JsonResponse
    {
        $this->requireRoles($request, ['TS', 'Admin']);

        $payload = $request->validate([
            'judul'                  => ['sometimes', 'required', 'string', 'max:255'],
            'deskripsi'              => ['sometimes', 'required', 'string'],
            'status'                 => ['sometimes', 'required', 'string', 'max:50'],
            'kategori'               => ['sometimes', 'required', 'string', 'max:100'],
            'priority'               => ['sometimes', 'required', 'in:low,medium,high'],
            'assigned_to'            => ['nullable', 'exists:users,id'],
            'start_time'             => ['nullable', 'date'],
            'end_time'               => ['nullable', 'date', 'after_or_equal:start_time'],
            'penyebab_permasalahan' => ['nullable', 'string'],
            'penyelesaian_masalah'  => ['nullable', 'string'],
            'impact'                => ['nullable', 'string'],
            'jumlah_agent_terdampak'=> ['nullable', 'integer', 'min:1'],
            'analisa'               => ['nullable', 'string'],
            'nomor_surat'           => ['nullable', 'string'],
            'kode'                  => ['nullable', 'string'],
            'id_task_sip'           => ['nullable', 'string'],
            'jenis_gangguan'        => ['nullable', 'string', 'in:Personal,Massal'],
        ]);

        $startTime = $payload['start_time'] ?? $gangguan->start_time;
        $endTime   = $payload['end_time']   ?? $gangguan->end_time;
        $payload['durasi'] = $this->calculateDuration($startTime, $endTime);

        // Auto-stamp: catat waktu dan TS yang menyelesaikan tiket
        $newStatus = $payload['status'] ?? null;
        
        // Auto assign petugas TS (Shift) saat mulai ditangani (in_progress)
        if ($newStatus === 'in_progress' && !isset($payload['assigned_to']) && is_null($gangguan->assigned_to)) {
            $payload['assigned_to'] = $request->user()->id;
        }

        if ($newStatus === 'closed' && $gangguan->status !== 'closed') {
            $payload['resolved_at'] = now();
            $payload['resolved_by'] = $request->user()->id;
            
            // Auto set end_time if not provided manually by TS
            if (!isset($payload['end_time']) && is_null($gangguan->end_time)) {
                $payload['end_time'] = now();
                $endTime = $payload['end_time'];
                $payload['durasi'] = $this->calculateDuration($startTime, $endTime);
            }
        }

        // Auto-stamp read_at jika belum ada (TS update tanpa buka detail dulu)
        if (is_null($gangguan->read_at)) {
            $payload['read_at'] = now();
            $payload['read_by'] = $request->user()->id;
        }

        $gangguan->update($payload);

        // Auto-Cascade Close to child tickets if parent ticket is closed
        if ($newStatus === 'closed' && $gangguan->children()->exists()) {
            $gangguan->children()->update([
                'status'                => 'closed',
                'resolved_at'           => $payload['resolved_at'] ?? now(),
                'resolved_by'           => $payload['resolved_by'] ?? $request->user()->id,
                'end_time'              => $payload['end_time'] ?? $endTime ?? now(),
                'durasi'                => $payload['durasi'] ?? $gangguan->durasi,
                'penyelesaian_masalah'  => $payload['penyelesaian_masalah'] ?? $gangguan->penyelesaian_masalah,
                'penyebab_permasalahan' => $payload['penyebab_permasalahan'] ?? $gangguan->penyebab_permasalahan,
            ]);
        }

        $gangguan = $gangguan->fresh([
            'creator:id,name',
            'assignee:id,name,signature',
            'reader:id,name',
            'resolver:id,name',
            'evidences',
            'parent:id,ticket_number,judul,status',
            'children:id,ticket_number,judul,status,created_by',
            'children.creator:id,name',
            'affectedAgents.user:id,name'
        ]);

        $cubicle = \App\Models\Cubicle::where('nama', $gangguan->kategori)->first();
        $gangguan->cubicle_ext = $cubicle->ext ?? '';
        $gangguan->cubicle_ip = $cubicle->ip ?? '';

        return response()->json($gangguan);
    }

    public function destroy(Request $request, Gangguan $gangguan): JsonResponse
    {
        $this->requireRoles($request, ['Admin', 'TS']);

        $gangguan->delete();

        return response()->json([
            'message' => 'Gangguan berhasil dihapus.',
        ]);
    }

    /**
     * Fase 1: Endpoint untuk mengambil daftar insiden massal/potensial aktif.
     */
    public function activeIncidents(Request $request): JsonResponse
    {
        $kategori = $request->query('kategori');
        $userId   = $request->user()?->id;

        $incidents = app(SpikeDetectorService::class)->getActiveIncidents($kategori, $userId);

        return response()->json($incidents);
    }

    /**
     * Fase 1: Tombol "Saya Mengalami Gangguan Ini Juga" (Me-Too).
     */
    public function joinIncident(Request $request, Gangguan $gangguan): JsonResponse
    {
        $this->requireRoles($request, ['Agent', 'TS', 'Admin']);

        $validated = $request->validate([
            'cubicle' => ['nullable', 'string', 'max:100'],
            'notes'   => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        // Cek apakah sudah pernah join
        $exists = GangguanAffectedAgent::where('gangguan_id', $gangguan->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists || $gangguan->created_by === $user->id) {
            return response()->json([
                'message'  => 'Anda sudah terdaftar pada laporan gangguan ini.',
                'gangguan' => $gangguan->fresh(['affectedAgents.user:id,name']),
            ], 200);
        }

        GangguanAffectedAgent::create([
            'gangguan_id'  => $gangguan->id,
            'user_id'      => $user->id,
            'agent_name'   => $user->name,
            'cubicle_name' => $validated['cubicle'] ?? null,
            'notes'        => $validated['notes'] ?? null,
        ]);

        // Hitung ulang total agent terdampak
        $totalAffected = GangguanAffectedAgent::where('gangguan_id', $gangguan->id)->count() + 1;
        $gangguan->update([
            'jumlah_agent_terdampak' => $totalAffected,
            'jenis_gangguan'         => 'Massal', // otomatis set massal jika sudah multi-agent
        ]);

        return response()->json([
            'message'                => 'Berhasil mencatat Anda sebagai agent terdampak. Terima kasih!',
            'jumlah_agent_terdampak' => $totalAffected,
            'gangguan'               => $gangguan->fresh(['affectedAgents.user:id,name']),
        ], 201);
    }

    /**
     * Fase 2: Bulk Merge Sub-Tickets ke Parent Mass Ticket.
     */
    public function bulkMerge(Request $request): JsonResponse
    {
        $this->requireRoles($request, ['TS', 'Admin']);

        $validated = $request->validate([
            'parent_id'  => ['nullable', 'exists:gangguans,id'],
            'child_ids'  => ['required', 'array', 'min:1'],
            'child_ids.*'=> ['exists:gangguans,id'],
            'new_parent' => ['nullable', 'array'],
            'new_parent.judul'    => ['nullable', 'string', 'max:255'],
            'new_parent.kategori' => ['nullable', 'string', 'max:100'],
            'new_parent.deskripsi'=> ['nullable', 'string'],
        ]);

        $childIds = $validated['child_ids'];
        $parent = null;

        if (!empty($validated['parent_id'])) {
            $parent = Gangguan::findOrFail($validated['parent_id']);
        } elseif (!empty($validated['new_parent'])) {
            // Buat parent ticket massal baru
            $kategori = $validated['new_parent']['kategori'] ?? 'MASSAL';
            $judul    = $validated['new_parent']['judul'] ?? "Gangguan Massal {$kategori}";
            $desc     = $validated['new_parent']['deskripsi'] ?? "Tiket konsolidasi gangguan massal untuk kategori {$kategori}";

            $parent = Gangguan::create([
                'ticket_number'          => $this->generateTicketNumber(),
                'judul'                  => $judul,
                'deskripsi'              => $desc,
                'kategori'               => $kategori,
                'jenis_gangguan'         => 'Massal',
                'status'                 => 'in_progress',
                'priority'               => 'high',
                'created_by'             => $request->user()->id,
                'assigned_to'            => $request->user()->id,
                'start_time'             => now(),
                'jumlah_agent_terdampak' => count($childIds) + 1,
            ]);
        } else {
            // Gunakan tiket pertama dari child_ids sebagai parent
            $parent = Gangguan::findOrFail($childIds[0]);
            $parent->update([
                'jenis_gangguan' => 'Massal',
                'priority'       => 'high',
            ]);
            $childIds = array_values(array_diff($childIds, [$parent->id]));
        }

        // Tautkan child tickets ke parent_id
        if (!empty($childIds)) {
            Gangguan::whereIn('id', $childIds)
                ->where('id', '!=', $parent->id)
                ->update([
                    'parent_id'           => $parent->id,
                    'is_potential_massal' => false,
                ]);
        }

        // Recalculate impacted agents count
        $childrenCount = Gangguan::where('parent_id', $parent->id)->count();
        $affectedCount = GangguanAffectedAgent::where('gangguan_id', $parent->id)->count();
        $totalImpacted = max($parent->jumlah_agent_terdampak ?? 1, $childrenCount + $affectedCount + 1);

        $parent->update([
            'jumlah_agent_terdampak' => $totalImpacted,
            'is_potential_massal'    => false,
        ]);

        return response()->json([
            'message' => 'Tiket berhasil digabungkan ke Gangguan Massal.',
            'parent'  => $parent->fresh(['creator:id,name', 'children.creator:id,name', 'affectedAgents']),
        ]);
    }

    /**
     * Fase 2: Unmerge Child Ticket dari Parent.
     */
    public function unmerge(Request $request, Gangguan $gangguan): JsonResponse
    {
        $this->requireRoles($request, ['TS', 'Admin']);

        $parentId = $gangguan->parent_id;

        $gangguan->update(['parent_id' => null]);

        if ($parentId) {
            $parent = Gangguan::find($parentId);
            if ($parent) {
                $childrenCount = Gangguan::where('parent_id', $parentId)->count();
                $affectedCount = GangguanAffectedAgent::where('gangguan_id', $parentId)->count();
                $parent->update([
                    'jumlah_agent_terdampak' => max(1, $childrenCount + $affectedCount + 1),
                ]);
            }
        }

        return response()->json([
            'message' => 'Tiket berhasil dilepaskan dari gangguan massal.',
        ]);
    }

    /**
     * Fase 3: Realtime Spike Alerts untuk TS Dashboard.
     */
    public function spikeAlerts(Request $request): JsonResponse
    {
        $this->requireRoles($request, ['Admin', 'TS']);

        $alerts = app(SpikeDetectorService::class)->getActiveSpikeAlerts();

        return response()->json($alerts);
    }

    /**
     * Get statistics & recent tickets handled by the currently authenticated TS user.
     */
    public function myTsStats(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Gangguan::where(function($q) use ($user) {
            $q->where('assigned_to', $user->id)
              ->orWhere('resolved_by', $user->id);
        });

        $totalHandled = (clone $query)->count();
        $totalBaTerbit = (clone $query)
            ->whereNotNull('id_task_sip')
            ->where('id_task_sip', '!=', '')
            ->where('id_task_sip', '!=', '-')
            ->whereNotNull('nomor_surat')
            ->where('nomor_surat', '!=', '')
            ->where('nomor_surat', '!=', '-')
            ->whereNotNull('kode')
            ->where('kode', '!=', '')
            ->where('kode', '!=', '-')
            ->count();
        $totalClosed  = $totalBaTerbit;
        $totalActive  = (clone $query)->whereIn('status', ['open', 'in_progress'])->count();
        $avgDuration  = (clone $query)->whereNotNull('durasi')->avg('durasi');

        $recentList = (clone $query)
            ->with(['creator:id,name', 'assignee:id,name,signature', 'evidences'])
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        return response()->json([
            'user'                 => $user,
            'total_handled'        => $totalHandled,
            'total_closed'         => $totalClosed,
            'total_ba_terbit'      => $totalBaTerbit,
            'total_active'         => $totalActive,
            'avg_duration_minutes' => round($avgDuration ?? 0, 1),
            'gangguan_list'        => $recentList,
        ]);
    }

    private function generateTicketNumber(): string
    {
        return 'TK-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4));
    }

    private function calculateDuration(?string $start, ?string $end): ?int
    {
        if (!$start || !$end) {
            return null;
        }

        return Carbon::parse($start)->diffInMinutes(Carbon::parse($end));
    }

    private function requireRoles(Request $request, array $roles): void
    {
        abort_unless($request->user()?->hasAnyRole($roles), 403, 'Anda tidak memiliki akses ke aksi ini.');
    }
}
