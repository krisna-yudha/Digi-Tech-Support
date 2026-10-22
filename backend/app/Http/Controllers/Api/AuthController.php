<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gangguan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $rawInput = trim($request->input('email'));
        $inputLower = strtolower($rawInput);
        $password = (string) $request->input('password');

        // Sanitized account name (replaces spaces with underscore, strips invalid chars)
        $cleanAccount = preg_replace('/[^a-z0-9._+\-]/', '', str_replace(' ', '_', $inputLower));

        // Case-insensitive user lookup matching email, @ts.internal, username part, or name
        $isSqlite = DB::getDriverName() === 'sqlite';
        $user = User::query()
            ->where(function ($q) use ($inputLower, $cleanAccount, $isSqlite) {
                $q->whereRaw('LOWER(email) = ?', [$inputLower])
                  ->orWhereRaw('LOWER(email) = ?', [$inputLower . '@ts.internal'])
                  ->orWhereRaw('LOWER(name) = ?', [$inputLower]);

                if (!$isSqlite) {
                    $q->orWhereRaw('LOWER(SUBSTRING_INDEX(email, "@", 1)) = ?', [$inputLower]);
                } else {
                    $q->orWhereRaw('LOWER(SUBSTR(email, 1, INSTR(email, "@") - 1)) = ?', [$inputLower]);
                }

                if (!empty($cleanAccount) && $cleanAccount !== $inputLower) {
                    $q->orWhereRaw('LOWER(email) = ?', [$cleanAccount . '@ts.internal']);
                    if (!$isSqlite) {
                        $q->orWhereRaw('LOWER(SUBSTRING_INDEX(email, "@", 1)) = ?', [$cleanAccount]);
                    } else {
                        $q->orWhereRaw('LOWER(SUBSTR(email, 1, INSTR(email, "@") - 1)) = ?', [$cleanAccount]);
                    }
                }
            })
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
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
}
