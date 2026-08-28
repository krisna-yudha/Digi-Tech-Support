<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cubicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CubicleController extends Controller
{
    public function index(): JsonResponse
    {
        $cubicles = Cubicle::orderBy('nama')->get();
        return response()->json($cubicles);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:2048'],
        ]);

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());
        // Remove UTF-8 BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        
        $lines = preg_split('/\r\n|\n|\r/', $content);
        $parsedCubicles = [];
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

            // Expecting: NAMA, EXT, IP
            if ($isFirstLine) {
                $isFirstLine = false;
                $firstCell = strtoupper(trim($row[0] ?? ''));
                if ($firstCell === 'NAMA' || $firstCell === 'CUBICLE') {
                    continue; // it's a header
                }
            }

            $nama = trim($row[0] ?? '');
            $ext  = trim($row[1] ?? '');
            $ip   = trim($row[2] ?? '');

            if (empty($nama)) {
                continue;
            }

            $parsedCubicles[] = [
                'nama' => $nama,
                'ext'  => $ext,
                'ip'   => $ip,
            ];
        }

        if (empty($parsedCubicles)) {
            return response()->json([
                'message' => 'Tidak ada data cubicle yang valid di dalam file. Periksa kembali format file CSV Anda.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $count = 0;
            foreach ($parsedCubicles as $cubicleData) {
                Cubicle::updateOrCreate(
                    ['nama' => $cubicleData['nama']],
                    [
                        'ext' => $cubicleData['ext'],
                        'ip'  => $cubicleData['ip'],
                    ]
                );
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
            'message' => "Berhasil mengimport {$count} data cubicle."
        ]);
    }
}
