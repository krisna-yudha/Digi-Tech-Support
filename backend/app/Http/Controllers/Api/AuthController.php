<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gangguan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $input = trim($request->input('email'));

        // If it's not an email (no @), treat it as an agent account name and append domain
        if (!str_contains($input, '@')) {
            $email = $input . '@ts.internal';
        } else {
            $email = $input;
        }

        $credentials = [
            'email'    => $email,
            'password' => $request->input('password'),
        ];

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Email atau password tidak valid.',
            ], 422);
        }

        $user = $request->user();
        $token = $user->createToken('api-token', ['*'], now()->addHours(4))->plainTextToken;

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
            ->where('created_by', $user->id)
            ->withCount('evidences')
            ->latest()
            ->limit(5)
            ->get([
                'id',
                'ticket_number',
                'judul',
                'status',
                'created_at',
                'updated_at',
            ]);

        $totalReports = Gangguan::query()
            ->where('created_by', $user->id)
            ->count();

        $latestStatus = Gangguan::query()
            ->where('created_by', $user->id)
            ->latest()
            ->value('status');

        return response()->json([
            'total_reports' => $totalReports,
            'latest_status' => $latestStatus,
            'latest_reports' => $latestReports,
        ]);
    }
}
