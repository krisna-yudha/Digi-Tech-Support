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

    /**
     * Parse CSV file content → return array of cubicle rows.
     */
    private function parseCsvContent(string $content): array
    {
        // Remove UTF-8 BOM
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        // Detect delimiter from first line
        $firstLine = fgets($handle);
        $delimiter = ',';
        $commaCount     = substr_count($firstLine, ',');
        $semicolonCount = substr_count($firstLine, ';');
        $tabCount       = substr_count($firstLine, "\t");
        if ($semicolonCount > $commaCount && $semicolonCount > $tabCount) $delimiter = ';';
        elseif ($tabCount > $commaCount && $tabCount > $semicolonCount)   $delimiter = "\t";
        rewind($handle);

        $parsed      = [];
        $headers     = null;

        // Helper: fuzzy column lookup by substring keyword
        $findCol = function (array $mapped, array $keywords): string {
            foreach ($keywords as $kw) {
                foreach ($mapped as $key => $val) {
                    if (str_contains($key, $kw)) return $val;
                }
            }
            return '';
        };

        while (($row = fgetcsv($handle, 10000, $delimiter)) !== false) {
            if (empty(array_filter($row))) continue;

            // First non-empty row = header detection
            if ($headers === null) {
                $firstCell = strtoupper(trim($row[0] ?? ''));
                // Header row: starts with NO / NAMA / CUBICLE / NOMOR
                if (!is_numeric($firstCell) || in_array($firstCell, ['NO', 'NAMA', 'CUBICLE', 'NOMOR']) || str_starts_with($firstCell, 'NO')) {
                    $headers = array_map(fn($h) => strtoupper(trim($h)), $row);
                    continue;
                }
                // No header → assume fixed order: No, Nomor Kubicle, Rochet, IP, Extension
                $headers = ['NO', 'NOMOR KUBICLE', 'ROCHET', 'IP', 'EXTENSION'];
            }

            // Map row to associative array by header
            $mapped = [];
            foreach ($headers as $i => $h) {
                $mapped[$h] = trim($row[$i] ?? '');
            }

            $nama   = $findCol($mapped, ['NOMOR', 'NAMA', 'CUBICLE']);
            $rochet = $findCol($mapped, ['ROCHET']);
            $ip     = $findCol($mapped, ['IP']);
            $ext    = $findCol($mapped, ['EXTENSION', 'EXT']);

            if (empty($nama)) continue;

            $parsed[] = [
                'nama'   => $nama,
                'rochet' => $rochet,
                'ext'    => $ext,
                'ip'     => $ip,
            ];
        }

        fclose($handle);
        return $parsed;
    }

    /**
     * Preview CSV import — parse only, no DB write.
     */
    public function previewImport(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:5120']]);

        $content = $request->file('file')->get();
        if ($content === false) {
            return response()->json(['message' => 'Gagal membaca isi file.'], 500);
        }

        $result = $this->parseCsvContent($content);

        if (empty($result)) {
            return response()->json([
                'message' => 'Tidak ada data cubicle yang valid. Pastikan CSV memiliki kolom: No, Nomor Kubicle, Rochet, IP, Extension.'
            ], 422);
        }

        // Flag existing cubicles
        $namas    = array_column($result, 'nama');
        $existing = Cubicle::whereIn('nama', $namas)->pluck('nama')->toArray();
        foreach ($result as &$row) {
            $row['exists'] = in_array($row['nama'], $existing);
        }

        return response()->json([
            'data'           => $result,
            'total'          => count($result),
            'existing_count' => count($existing),
        ]);
    }

    /**
     * Commit import from pre-parsed JSON payload — called per batch from frontend.
     */
    public function importBatch(Request $request): JsonResponse
    {
        $request->validate([
            'cubicles'          => ['required', 'array', 'min:1'],
            'cubicles.*.nama'   => ['required', 'string'],
            'cubicles.*.rochet' => ['nullable', 'string'],
            'cubicles.*.ext'    => ['nullable', 'string'],
            'cubicles.*.ip'     => ['nullable', 'string'],
        ]);

        DB::beginTransaction();
        try {
            $count = 0;
            foreach ($request->input('cubicles') as $row) {
                Cubicle::updateOrCreate(
                    ['nama' => $row['nama']],
                    [
                        'rochet' => $row['rochet'] ?? '',
                        'ext'    => $row['ext']    ?? '',
                        'ip'     => $row['ip']     ?? '',
                    ]
                );
                $count++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal memproses batch: ' . $e->getMessage()], 422);
        }

        return response()->json(['message' => "Berhasil mengimport {$count} data cubicle."]);
    }
}
