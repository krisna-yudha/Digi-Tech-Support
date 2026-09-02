<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gangguan;
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

        $query = Gangguan::with(['creator:id,name', 'assignee:id,name'])
            ->orderBy($sortBy, $sortDir);

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
            'cubicle' => ['required', 'string', 'max:100'],
            'agent_name' => ['required', 'string', 'max:255'],
            'problem' => ['required', 'string'],
            'judul' => ['nullable', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
            'jenis_gangguan' => ['nullable', 'string', 'in:Personal,Massal'],
            'kategori' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
        ]);

        $cubicle = $payload['cubicle'];
        $agentName = $payload['agent_name'];
        $problem = $payload['problem'];

        $payload['judul'] = $payload['judul'] ?? "Gangguan {$cubicle} - {$agentName}";
        $payload['deskripsi'] = $payload['deskripsi'] ?? "Cubicle: {$cubicle}\nAgent: {$agentName}\nProblem: {$problem}";
        $payload['kategori'] = $payload['kategori'] ?? $cubicle;
        $payload['ticket_number'] = $this->generateTicketNumber();
        $payload['created_by'] = $request->user()->id;
        $payload['status'] = $payload['status'] ?? 'open';
        $payload['jenis_gangguan'] = $payload['jenis_gangguan'] ?? 'Personal';
        $payload['priority'] = $payload['priority'] ?? 'medium';
        $payload['start_time'] = $payload['start_time'] ?? now();
        $payload['durasi'] = $this->calculateDuration($payload['start_time'] ?? null, $payload['end_time'] ?? null);

        unset($payload['cubicle'], $payload['agent_name'], $payload['problem']);

        $gangguan = Gangguan::create($payload);

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

        $gangguan->load(['creator:id,name', 'assignee:id,name,signature', 'reader:id,name', 'resolver:id,name', 'evidences']);

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

        // Pastikan laporan ini milik agent yang sedang login
        abort_unless(
            $gangguan->created_by === $request->user()->id,
            403,
            'Anda tidak memiliki akses ke laporan ini.'
        );

        $gangguan->load(['evidences']);

        return response()->json($gangguan);
    }

    public function update(Request $request, Gangguan $gangguan): JsonResponse
    {
        $this->requireRoles($request, ['TS', 'Admin']);

        $payload = $request->validate([
            'judul'      => ['sometimes', 'required', 'string', 'max:255'],
            'deskripsi'  => ['sometimes', 'required', 'string'],
            'status'     => ['sometimes', 'required', 'string', 'max:50'],
            'kategori'   => ['sometimes', 'required', 'string', 'max:100'],
            'priority'   => ['sometimes', 'required', 'in:low,medium,high'],
            'assigned_to'=> ['nullable', 'exists:users,id'],
            'start_time' => ['nullable', 'date'],
            'end_time'   => ['nullable', 'date', 'after_or_equal:start_time'],
            'penyebab_permasalahan' => ['nullable', 'string'],
            'penyelesaian_masalah'  => ['nullable', 'string'],
            'impact'                => ['nullable', 'string'],
            'jumlah_agent_terdampak'=> ['nullable', 'integer', 'min:1'],
            'analisa'               => ['nullable', 'string'],
            'nomor_surat'           => ['nullable', 'string'],
            'kode'                  => ['nullable', 'string'],
            'id_task_sip'           => ['nullable', 'string'],
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

        $gangguan = $gangguan->fresh(['creator:id,name', 'assignee:id,name,signature', 'reader:id,name', 'resolver:id,name', 'evidences']);
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
