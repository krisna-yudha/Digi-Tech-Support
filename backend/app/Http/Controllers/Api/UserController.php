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

    /**
     * Parse CSV file content and return structured user data.
     */
    private function parseCsvContent(string $content): array|\Illuminate\Http\JsonResponse
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
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
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $parsedUsers = [];
        $isFirstLine = true;
        $map = ['name' => -1, 'gender' => -1, 'jabatan' => -1, 'account' => -1, 'password' => -1];

        while (($row = fgetcsv($handle, 10000, $delimiter)) !== false) {
            if (empty(array_filter($row))) continue;

            if ($isFirstLine) {
                $isFirstLine = false;
                $firstCell = strtoupper(trim($row[0] ?? ''));
                if ($firstCell === 'NO' || !is_numeric($firstCell)) {
                    foreach ($row as $idx => $colName) {
                        $col = strtoupper(trim($colName));
                        if ($col === 'NAMA' || $col === 'NAME') $map['name'] = $idx;
                        elseif (in_array($col, ['JK', 'GENDER', 'JENIS KELAMIN'])) $map['gender'] = $idx;
                        elseif (in_array($col, ['JABATAN', 'ROLE'])) $map['jabatan'] = $idx;
                        elseif (in_array($col, ['ACCOUNT', 'AKUN', 'EMAIL', 'USERNAME', 'USER'])) $map['account'] = $idx;
                        elseif (in_array($col, ['PASSWORD', 'PASS'])) $map['password'] = $idx;
                    }
                    continue;
                }
            }

            $name = ''; $gender = ''; $jabatan = ''; $account = ''; $password = '';

            if ($map['name'] !== -1 && $map['account'] !== -1) {
                $name     = trim($row[$map['name']] ?? '');
                $gender   = $map['gender'] !== -1 ? trim($row[$map['gender']] ?? '') : '';
                $jabatan  = $map['jabatan'] !== -1 ? trim($row[$map['jabatan']] ?? '') : '';
                $account  = trim($row[$map['account']] ?? '');
                $password = $map['password'] !== -1 && !empty(trim($row[$map['password']] ?? ''))
                    ? trim($row[$map['password']]) : '12345';
            } else {
                if (count($row) >= 5 && (is_numeric($row[0]) || empty($row[0]))) {
                    [$_, $name, $gender, $jabatan, $account] = $row;
                    $password = !empty(trim($row[5] ?? '')) ? trim($row[5]) : '12345';
                } elseif (count($row) >= 4) {
                    [$name, $gender, $jabatan, $account] = $row;
                    $password = !empty(trim($row[4] ?? '')) ? trim($row[4]) : '12345';
                }
            }

            if (empty(trim($name)) || empty(trim($account))) continue;

            // Sanitasi account: hapus spasi dan karakter yang tidak valid di local-part email
            $accountClean = trim($account);
            if (str_contains($accountClean, '@')) {
                // Sudah ada domain, gunakan apa adanya tapi trim spasi
                $parts = explode('@', $accountClean, 2);
                $localPart = preg_replace('/\s+/', '', $parts[0]);
                $email = $localPart . '@' . trim($parts[1]);
            } else {
                // Bersihkan local part: ganti spasi dengan underscore, hilangkan karakter aneh
                $localPart = preg_replace('/[^a-zA-Z0-9._+\-]/', '', str_replace(' ', '_', $accountClean));
                $email = $localPart . '@ts.internal';
            }

            if (empty($email) || strlen($email) < 3) continue;

            $parsedUsers[] = [
                'email'   => $email,
                'name'    => trim($name),
                'gender'  => trim($gender),
                'jabatan' => trim($jabatan),
                'password'=> trim($password),
            ];
        }
        fclose($handle);

        return $parsedUsers;
    }

    /**
     * Preview CSV import (parse only, no DB write).
     */
    public function previewImport(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:5120']]);

        $content = $request->file('file')->get();
        if ($content === false) {
            return response()->json(['message' => 'Gagal membaca isi file.'], 500);
        }

        $result = $this->parseCsvContent($content);
        if ($result instanceof \Illuminate\Http\JsonResponse) return $result;

        if (empty($result)) {
            return response()->json(['message' => 'Tidak ada data agent yang valid. Periksa kembali format CSV Anda.'], 422);
        }

        // Flag existing users
        $emails = array_column($result, 'email');
        $existing = User::whereIn('email', $emails)->pluck('email')->toArray();
        foreach ($result as &$row) {
            $row['exists'] = in_array($row['email'], $existing);
        }

        return response()->json([
            'data'  => $result,
            'total' => count($result),
            'existing_count' => count($existing),
        ]);
    }

    /**
     * Commit import from pre-parsed JSON payload (no file re-read needed).
     */
    public function importAgents(Request $request): JsonResponse
    {
        $request->validate([
            'users' => ['required', 'array', 'min:1'],
            'users.*.name'    => ['required', 'string'],
            'users.*.email'   => ['required', 'string', 'min:3'],  // tidak pakai rule 'email' agar @ts.internal lolos
            'users.*.gender'  => ['nullable', 'string'],
            'users.*.jabatan' => ['nullable', 'string'],
            'users.*.password'=> ['nullable', 'string'],
        ]);

        DB::beginTransaction();
        try {
            $count = 0;
            foreach ($request->input('users') as $userData) {
                $user = User::updateOrCreate(
                    ['email' => $userData['email']],
                    [
                        'name'     => $userData['name'],
                        'gender'   => $userData['gender'] ?? '',
                        'jabatan'  => $userData['jabatan'] ?? '',
                        'password' => Hash::make(!empty($userData['password']) ? $userData['password'] : '12345'),
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
            return response()->json(['message' => 'Gagal memproses import: ' . $e->getMessage()], 422);
        }

        return response()->json(['message' => "Berhasil mengimport {$count} data agent."]);
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
