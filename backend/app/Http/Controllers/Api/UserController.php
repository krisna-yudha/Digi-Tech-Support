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
        
        $handle = fopen($file->getRealPath(), 'r');
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            return response()->json(['message' => 'File kosong atau tidak valid.'], 422);
        }
        
        // Deteksi delimiter dari baris pertama
        $delimiter = ',';
        $commaCount = substr_count($firstLine, ',');
        $semicolonCount = substr_count($firstLine, ';');
        $tabCount = substr_count($firstLine, "\t");

        if ($semicolonCount > $commaCount && $semicolonCount > $tabCount) {
            $delimiter = ';';
        } elseif ($tabCount > $commaCount && $tabCount > $semicolonCount) {
            $delimiter = "\t";
        }
        
        rewind($handle);
        // Lewati BOM (Byte Order Mark) jika ada
        $content = file_get_contents($file->getRealPath());
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            fseek($handle, 3);
        }

        $parsedUsers = [];
        $isFirstLine = true;
        $map = [
            'name' => -1,
            'gender' => -1,
            'jabatan' => -1,
            'account' => -1,
            'password' => -1,
        ];

        while (($row = fgetcsv($handle, 10000, $delimiter)) !== false) {
            // Abaikan baris kosong
            if (empty(array_filter($row))) {
                continue;
            }

            // Identifikasi baris header
            if ($isFirstLine) {
                $isFirstLine = false;
                $firstCell = strtoupper(trim($row[0] ?? ''));
                if ($firstCell === 'NO' || !is_numeric($firstCell)) {
                    // Map kolom berdasarkan nama header
                    foreach($row as $idx => $colName) {
                         $colName = strtoupper(trim($colName));
                         if ($colName === 'NAMA' || $colName === 'NAME') $map['name'] = $idx;
                         elseif ($colName === 'JK' || $colName === 'GENDER' || $colName === 'JENIS KELAMIN') $map['gender'] = $idx;
                         elseif ($colName === 'JABATAN' || $colName === 'ROLE') $map['jabatan'] = $idx;
                         elseif (in_array($colName, ['ACCOUNT', 'AKUN', 'EMAIL', 'USERNAME', 'USER'])) $map['account'] = $idx;
                         elseif ($colName === 'PASSWORD' || $colName === 'PASS') $map['password'] = $idx;
                    }
                    continue; 
                }
            }

            $name = ''; $gender = ''; $jabatan = ''; $account = ''; $password = '';

            // Gunakan mapping header jika ditemukan
            if ($map['name'] !== -1 && $map['account'] !== -1) {
                $name     = trim($row[$map['name']] ?? '');
                $gender   = $map['gender'] !== -1 ? trim($row[$map['gender']] ?? '') : '';
                $jabatan  = $map['jabatan'] !== -1 ? trim($row[$map['jabatan']] ?? '') : '';
                $account  = trim($row[$map['account']] ?? '');
                $password = $map['password'] !== -1 && !empty(trim($row[$map['password']] ?? '')) ? trim($row[$map['password']]) : '12345';
            } else {
                // Fallback jika tidak ada header (menebak dari posisi kolom)
                if (count($row) >= 5 && (is_numeric($row[0]) || empty($row[0]))) {
                    $name     = trim($row[1] ?? '');
                    $gender   = trim($row[2] ?? '');
                    $jabatan  = trim($row[3] ?? '');
                    $account  = trim($row[4] ?? '');
                    $password = !empty(trim($row[5] ?? '')) ? trim($row[5]) : '12345';
                } elseif (count($row) >= 4) {
                    $name     = trim($row[0] ?? '');
                    $gender   = trim($row[1] ?? '');
                    $jabatan  = trim($row[2] ?? '');
                    $account  = trim($row[3] ?? '');
                    $password = !empty(trim($row[4] ?? '')) ? trim($row[4]) : '12345';
                }
            }

            if (empty($name) || empty($account)) {
                continue;
            }

            // Simpan email dengan domain internal
            $email = str_contains($account, '@') ? $account : $account . '@ts.internal';

            $parsedUsers[] = [
                'email' => $email,
                'name' => $name,
                'gender' => $gender,
                'jabatan' => $jabatan,
                'password' => $password,
            ];
        }
        fclose($handle);

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
