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

        $data = Gangguan::with(['creator:id,name', 'assignee:id,name'])
            ->latest()
            ->paginate(10);

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
        $payload['priority'] = $payload['priority'] ?? 'medium';
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

        $gangguan->load(['creator:id,name', 'assignee:id,name', 'reader:id,name', 'resolver:id,name', 'evidences']);

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
        $this->requireRoles($request, ['TS']);

        $payload = $request->validate([
            'judul'      => ['sometimes', 'required', 'string', 'max:255'],
            'deskripsi'  => ['sometimes', 'required', 'string'],
            'status'     => ['sometimes', 'required', 'string', 'max:50'],
            'kategori'   => ['sometimes', 'required', 'string', 'max:100'],
            'priority'   => ['sometimes', 'required', 'in:low,medium,high'],
            'assigned_to'=> ['nullable', 'exists:users,id'],
            'start_time' => ['nullable', 'date'],
            'end_time'   => ['nullable', 'date', 'after_or_equal:start_time'],
        ]);

        $startTime = $payload['start_time'] ?? $gangguan->start_time;
        $endTime   = $payload['end_time']   ?? $gangguan->end_time;
        $payload['durasi'] = $this->calculateDuration($startTime, $endTime);

        // Auto-stamp: catat waktu dan TS yang menyelesaikan tiket
        $newStatus = $payload['status'] ?? null;
        if ($newStatus === 'closed' && $gangguan->status !== 'closed') {
            $payload['resolved_at'] = now();
            $payload['resolved_by'] = $request->user()->id;
        }

        // Auto-stamp read_at jika belum ada (TS update tanpa buka detail dulu)
        if (is_null($gangguan->read_at)) {
            $payload['read_at'] = now();
            $payload['read_by'] = $request->user()->id;
        }

        $gangguan->update($payload);

        return response()->json($gangguan->fresh(['creator:id,name', 'assignee:id,name', 'reader:id,name', 'resolver:id,name', 'evidences']));
    }

    public function destroy(Request $request, Gangguan $gangguan): JsonResponse
    {
        $this->requireRoles($request, ['Admin']);

        $gangguan->delete();

        return response()->json([
            'message' => 'Gangguan berhasil dihapus.',
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
