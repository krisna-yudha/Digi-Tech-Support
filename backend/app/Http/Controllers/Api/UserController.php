<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('roles');

        if ($request->has('role')) {
            $query->role($request->input('role'));
        }

        $users = $query->orderBy('name')->get();

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:5',
            'gender' => 'nullable|string|in:PRIA,WANITA,LAKI-LAKI,PEREMPUAN',
            'jabatan' => 'nullable|string|max:255',
            'role' => 'required|string|in:Admin,TS,Agent'
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'gender' => $validated['gender'],
            'jabatan' => $validated['jabatan'],
        ]);

        $user->assignRole($validated['role']);

        return response()->json([
            'message' => "Akun {$user->name} berhasil ditambahkan."
        ], 201);
    }

    public function importAgents(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:2048'],
        ]);

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());
        // Remove UTF-8 BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        
        $lines = preg_split('/\r\n|\n|\r/', $content);
        $parsedUsers = [];
        $isFirstLine = true;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $delimiter = ',';
            if (str_contains($line, "\t")) {
                $delimiter = "\t";
            } elseif (str_contains($line, ';')) {
                $delimiter = ';';
            }

            $row = str_getcsv($line, $delimiter);

            // Skip header row (NO, NAMA, JK, JABATAN, Account)
            if ($isFirstLine) {
                $isFirstLine = false;
                $firstCell = strtoupper(trim($row[0] ?? ''));
                if ($firstCell === 'NO' || !is_numeric($firstCell)) {
                    continue; // it's a header
                }
            }

            $name = ''; $gender = ''; $jabatan = ''; $account = ''; $password = '';

            // Format: NO | NAMA | JK | JABATAN | Account | password
            if (count($row) >= 5 && is_numeric($row[0])) {
                $name     = trim($row[1] ?? '');
                $gender   = trim($row[2] ?? '');
                $jabatan  = trim($row[3] ?? '');
                $account  = trim($row[4] ?? '');
                $password = !empty(trim($row[5] ?? '')) ? trim($row[5]) : '12345';
            } elseif (count($row) >= 4) {
                // Legacy format: NAMA | JK | JABATAN | Account | password
                $name     = trim($row[0] ?? '');
                $gender   = trim($row[1] ?? '');
                $jabatan  = trim($row[2] ?? '');
                $account  = trim($row[3] ?? '');
                $password = !empty(trim($row[4] ?? '')) ? trim($row[4]) : '12345';
            }

            if (empty($name) || empty($account)) {
                continue;
            }

            // Store email as account@ts.internal
            $email = $account . '@ts.internal';

            $parsedUsers[] = [
                'email' => $email,
                'name' => $name,
                'gender' => $gender,
                'jabatan' => $jabatan,
                'password' => $password,
            ];
        }

        if (empty($parsedUsers)) {
            return response()->json([
                'message' => 'Tidak ada data agent yang valid di dalam file. Periksa kembali format file CSV Anda.'
            ], 422);
        }

        $emailsToImport = array_column($parsedUsers, 'email');
        $existingUsers = User::whereIn('email', $emailsToImport)->pluck('email')->toArray();
        $overwrite = $request->boolean('overwrite', false);

        if (count($existingUsers) > 0 && !$overwrite) {
            return response()->json([
                'message' => count($existingUsers) . ' user sudah terdaftar di sistem. Apakah Anda ingin melanjutkan dan menimpa data mereka?',
                'require_confirmation' => true
            ], 409);
        }

        DB::beginTransaction();
        try {
            $count = 0;
            foreach ($parsedUsers as $userData) {
                $user = User::updateOrCreate(
                    ['email' => $userData['email']],
                    [
                        'name'     => $userData['name'],
                        'gender'   => $userData['gender'],
                        'jabatan'  => $userData['jabatan'],
                        'password' => Hash::make($userData['password']),
                    ]
                );

                if (!$user->hasRole('Agent')) {
                    $user->assignRole('Agent');
                }

                $count++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memproses import data: ' . $e->getMessage()
            ], 422);
        }

        return response()->json([
            'message' => "Berhasil mengimport {$count} data agent."
        ]);
    }

    public function destroy(User $user, Request $request): JsonResponse
    {
        // Prevent deleting yourself
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Tidak dapat menghapus akun sendiri.'], 422);
        }

        // Remove tokens before deleting
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => "Akun {$user->name} berhasil dihapus."]);
    }
}
