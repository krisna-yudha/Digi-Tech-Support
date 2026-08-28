<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gangguan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SummaryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        return response()->json($this->buildSummary($startDate, $endDate));
    }

    public function daily(): JsonResponse
    {
        $key = 'summary:daily:' . now()->toDateString();
        return response()->json(Cache::remember($key, 300, fn () => $this->groupByPeriod('day')));
    }

    public function weekly(): JsonResponse
    {
        $key = 'summary:weekly:' . now()->format('o-W');
        return response()->json(Cache::remember($key, 300, fn () => $this->groupByPeriod('week')));
    }

    public function monthly(): JsonResponse
    {
        $key = 'summary:monthly:' . now()->format('Y-m');
        return response()->json(Cache::remember($key, 300, fn () => $this->groupByPeriod('month')));
    }

    private function buildSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Gangguan::query();

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        } elseif ($startDate) {
            $query->where('created_at', '>=', $startDate . ' 00:00:00');
        } elseif ($endDate) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        return [
            'total' => (clone $query)->count(),
            'open' => (clone $query)->where('status', 'open')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'closed' => (clone $query)->where('status', 'closed')->count(),
            'by_priority' => (clone $query)->selectRaw('priority, COUNT(*) as total')
                ->groupBy('priority')
                ->pluck('total', 'priority'),
            'by_kategori' => (clone $query)->selectRaw('kategori, COUNT(*) as total')
                ->groupBy('kategori')
                ->pluck('total', 'kategori'),
            'total_downtime' => (int) (clone $query)->sum('durasi'),
            'total_agent_terdampak' => (int) (clone $query)->where('jenis_gangguan', 'Massal')->sum('jumlah_agent_terdampak'),
        ];
    }

    private function groupByPeriod(string $period): array
    {
        $query = Gangguan::query();

        if ($period === 'day') {
            $query->whereDate('created_at', now()->toDateString());
        } elseif ($period === 'week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } else {
            $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
        }

        return [
            'period' => $period,
            'total' => $query->count(),
            'by_status' => $query->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ];
    }
}
