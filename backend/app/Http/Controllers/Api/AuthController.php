<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gangguan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $rawInput = trim((string) $request->input('email'));
        $inputLower = strtolower($rawInput);
        $password = (string) $request->input('password');

        // Extract local part if user typed @domain or @ts.internal
        $localPart = str_contains($inputLower, '@')
            ? explode('@', $inputLower, 2)[0]
            : $inputLower;

        // Common variations: dots <-> spaces <-> underscores
        $withDot = str_replace(['_', ' '], '.', $localPart);
        $withUnderscore = str_replace(['.', ' '], '_', $localPart);
        $withSpace = str_replace(['.', '_'], ' ', $localPart);
        $cleanAlphanumeric = preg_replace('/[^a-z0-9]/', '', $localPart);

        $isSqlite = DB::getDriverName() === 'sqlite';

        $user = User::query()
            ->where(function ($q) use ($inputLower, $localPart, $withDot, $withUnderscore, $withSpace, $cleanAlphanumeric, $isSqlite) {
                // Exact full email or email with domain
                $q->whereRaw('LOWER(email) = ?', [$inputLower])
                  ->orWhereRaw('LOWER(email) = ?', [$inputLower . '@ts.internal'])
                  ->orWhereRaw('LOWER(email) = ?', [$localPart . '@ts.internal'])
                  ->orWhereRaw('LOWER(email) = ?', [$withDot . '@ts.internal'])
                  ->orWhereRaw('LOWER(email) = ?', [$withUnderscore . '@ts.internal'])
                  ->orWhereRaw('LOWER(name) = ?', [$inputLower])
                  ->orWhereRaw('LOWER(name) = ?', [$withSpace]);

                // Match local-part before '@'
                if (!$isSqlite) {
                    $q->orWhereRaw('LOWER(SUBSTRING_INDEX(email, "@", 1)) = ?', [$localPart])
                      ->orWhereRaw('LOWER(SUBSTRING_INDEX(email, "@", 1)) = ?', [$withDot])
                      ->orWhereRaw('LOWER(SUBSTRING_INDEX(email, "@", 1)) = ?', [$withUnderscore]);
                } else {
                    $q->orWhereRaw('LOWER(SUBSTR(email, 1, INSTR(email, "@") - 1)) = ?', [$localPart])
                      ->orWhereRaw('LOWER(SUBSTR(email, 1, INSTR(email, "@") - 1)) = ?', [$withDot])
                      ->orWhereRaw('LOWER(SUBSTR(email, 1, INSTR(email, "@") - 1)) = ?', [$withUnderscore]);
                }

                // Match stripped alphanumeric if not empty
                if (!empty($cleanAlphanumeric) && strlen($cleanAlphanumeric) >= 3) {
                    $q->orWhereRaw("LOWER(REPLACE(REPLACE(REPLACE(email, '.', ''), '_', ''), '-', '')) = ?", [$cleanAlphanumeric])
                      ->orWhereRaw("LOWER(REPLACE(REPLACE(REPLACE(email, '.', ''), '_', ''), '-', '')) = ?", [$cleanAlphanumeric . 'tsinternal']);
                }
            })
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Email atau password tidak valid.',
            ], 422);
        }

        // Toleransi huruf besar/kecil (case-insensitive) pada password
        $passwordMatches = Hash::check($password, $user->password)
            || Hash::check(strtolower($password), $user->password)
            || Hash::check(strtoupper($password), $user->password)
            || Hash::check(ucfirst(strtolower($password)), $user->password)
            || Hash::check(lcfirst(strtoupper($password)), $user->password);

        // Fallback jika password di-hash menggunakan nama akun user
        if (!$passwordMatches && !empty($user->email)) {
            $accountPart = explode('@', $user->email)[0];
            $passwordMatches = (
                (strcasecmp($password, $accountPart) === 0 && Hash::check($accountPart, $user->password))
                || (strcasecmp($password, strtolower($accountPart)) === 0 && Hash::check(strtolower($accountPart), $user->password))
                || (strcasecmp($password, strtoupper($accountPart)) === 0 && Hash::check(strtoupper($accountPart), $user->password))
            );
        }

        if (!$passwordMatches) {
            return response()->json([
                'message' => 'Email atau password tidak valid.',
            ], 422);
        }

        $remember = $request->boolean('remember');
        $tokenDuration = $remember ? now()->addHours(24) : now()->addHours(4);
        $token = $user->createToken('api-token', ['*'], $tokenDuration)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->values(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values(),
        ]);
    }

    public function agentDashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user?->hasRole('Agent'), 403, 'Anda tidak memiliki akses ke dashboard ini.');

        $latestReports = Gangguan::query()
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('affectedAgents', fn($sq) => $sq->where('user_id', $user->id));
            })
            ->withCount('evidences')
            ->latest()
            ->limit(10)
            ->get([
                'id',
                'ticket_number',
                'judul',
                'status',
                'jenis_gangguan',
                'kategori',
                'created_at',
                'updated_at',
            ]);

        $totalReports = Gangguan::query()
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('affectedAgents', fn($sq) => $sq->where('user_id', $user->id));
            })
            ->count();

        $latestStatus = Gangguan::query()
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('affectedAgents', fn($sq) => $sq->where('user_id', $user->id));
            })
            ->latest()
            ->value('status');

        return response()->json([
            'total_reports' => $totalReports,
            'latest_status' => $latestStatus,
            'latest_reports' => $latestReports,
        ]);
    }

    /**
     * Hook dan sinkronisasi tanda waktu resmi dari server BMKG (JamServer.php / JamServerFD.html)
     */
    public function getBmkgTime(): JsonResponse
    {
        $startMicro = microtime(true);
        $bmkgUrl = 'https://jam.bmkg.go.id/JamServer.php';
        $bmkgWebUrl = 'https://jam.bmkg.go.id/JamServerFD.html';

        try {
            $response = Http::timeout(4)
                ->withoutVerifying()
                ->get($bmkgUrl);

            if ($response->successful()) {
                $body = $response->body();
                // Format dari BMKG: var servertimeOBJ = new Date(2026,10-1,23,19,15,38);var TimeZone = 'WIB';
                if (preg_match('/new Date\((\d+),\s*(\d+)(?:-1)?,\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)\)/i', $body, $m)) {
                    $year   = (int) $m[1];
                    $month  = (int) $m[2];
                    $day    = (int) $m[3];
                    $hour   = (int) $m[4];
                    $minute = (int) $m[5];
                    $second = (int) $m[6];

                    $tz = 'WIB';
                    if (preg_match('/TimeZone\s*=\s*[\'"]([^\'"]+)[\'"]/i', $body, $tzMatch)) {
                        $tz = $tzMatch[1];
                    }

                    $dt = Carbon::create($year, $month, $day, $hour, $minute, $second, 'Asia/Jakarta');
                    $timestampMs = $dt->getTimestamp() * 1000;
                    $latencyMs = round((microtime(true) - $startMicro) * 1000);

                    return response()->json([
                        'success'       => true,
                        'source'        => 'bmkg',
                        'source_name'   => 'Server Waktu BMKG RI',
                        'url'           => $bmkgWebUrl,
                        'timezone'      => $tz,
                        'timestamp_ms'  => $timestampMs,
                        'iso_time'      => $dt->toIso8601String(),
                        'latency_ms'    => $latencyMs,
                        'server_time'   => now('Asia/Jakarta')->toIso8601String(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // BMKG timeout atau offline: gunakan fallback waktu server lokal
        }

        $fallbackDt = now('Asia/Jakarta');
        return response()->json([
            'success'       => true,
            'source'        => 'system_fallback',
            'source_name'   => 'Server Sistem (NTP Sinkron)',
            'url'           => $bmkgWebUrl,
            'timezone'      => 'WIB',
            'timestamp_ms'  => $fallbackDt->getTimestamp() * 1000,
            'iso_time'      => $fallbackDt->toIso8601String(),
            'latency_ms'    => round((microtime(true) - $startMicro) * 1000),
            'server_time'   => $fallbackDt->toIso8601String(),
            'notice'        => 'Server BMKG sedang tidak dapat dijangkau secara langsung, menggunakan waktu sinkronisasi cadangan.',
        ]);
    }
}
