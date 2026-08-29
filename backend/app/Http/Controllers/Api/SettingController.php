<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * Get default BA Template & settings key-value pairs.
     */
    public function index(): JsonResponse
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        // Defaults
        $defaults = [
            'ba_brand_name'            => 'PLN Icon Plus',
            'ba_departemen'            => 'Divisi Perencanaan Ops Ritel',
            'ba_title'                 => 'BERITA ACARA KRONOLOGIS GANGGUAN APLIKASI/JARINGAN',
            'ba_location'              => 'SEMARANG',
            'ba_koord_name'            => 'AHMAD ZAENAL ARIFIN',
            'ba_koord_title'           => 'KOORDINATOR',
            'ba_ts_title'              => 'TECHNICAL SUPPORT',
            'ba_show_evidence'         => 'true',
            'ba_logo'                  => '',
            'ba_koord_signature'       => '',
        ];

        $merged = array_merge($defaults, $settings);

        // Append full URLs for images
        $merged['ba_logo_url'] = !empty($merged['ba_logo']) 
            ? asset('storage/' . $merged['ba_logo']) 
            : null;

        $merged['ba_koord_signature_url'] = !empty($merged['ba_koord_signature']) 
            ? asset('storage/' . $merged['ba_koord_signature']) 
            : null;

        return response()->json($merged);
    }

    /**
     * Update settings key-value pairs.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ba_brand_name'    => ['nullable', 'string'],
            'ba_departemen'    => ['nullable', 'string'],
            'ba_title'         => ['nullable', 'string'],
            'ba_location'      => ['nullable', 'string'],
            'ba_koord_name'    => ['nullable', 'string'],
            'ba_koord_title'   => ['nullable', 'string'],
            'ba_ts_title'      => ['nullable', 'string'],
            'ba_show_evidence' => ['nullable', 'string'],
        ]);

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value ?? '']
            );
        }

        return response()->json([
            'message' => 'Pengaturan template Berita Acara berhasil disimpan.'
        ]);
    }

    /**
     * Upload Logo KOP image.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
        ]);

        $path = $request->file('logo')->store('settings', 'public');

        // Remove old logo if exists
        $old = Setting::where('key', 'ba_logo')->first();
        if ($old && $old->value && Storage::disk('public')->exists($old->value)) {
            Storage::disk('public')->delete($old->value);
        }

        Setting::updateOrCreate(['key' => 'ba_logo'], ['value' => $path]);

        return response()->json([
            'message'  => 'Logo KOP berhasil diunggah.',
            'logo_url' => asset('storage/' . $path),
        ]);
    }

    /**
     * Upload Koordinator Signature image.
     */
    public function uploadKoordSignature(Request $request): JsonResponse
    {
        $request->validate([
            'signature' => ['required', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
        ]);

        $path = $request->file('signature')->store('signatures', 'public');

        // Remove old signature if exists
        $old = Setting::where('key', 'ba_koord_signature')->first();
        if ($old && $old->value && Storage::disk('public')->exists($old->value)) {
            Storage::disk('public')->delete($old->value);
        }

        Setting::updateOrCreate(['key' => 'ba_koord_signature'], ['value' => $path]);

        return response()->json([
            'message'       => 'Tanda tangan Koordinator berhasil diunggah.',
            'signature_url' => asset('storage/' . $path),
        ]);
    }

    /**
     * Delete Logo KOP image.
     */
    public function deleteLogo(): JsonResponse
    {
        $old = Setting::where('key', 'ba_logo')->first();
        if ($old && $old->value && Storage::disk('public')->exists($old->value)) {
            Storage::disk('public')->delete($old->value);
        }
        if ($old) {
            $old->value = '';
            $old->save();
        }

        return response()->json([
            'message' => 'Logo KOP berhasil dihapus.'
        ]);
    }

    /**
     * Delete Koordinator Signature image.
     */
    public function deleteKoordSignature(): JsonResponse
    {
        $old = Setting::where('key', 'ba_koord_signature')->first();
        if ($old && $old->value && Storage::disk('public')->exists($old->value)) {
            Storage::disk('public')->delete($old->value);
        }
        if ($old) {
            $old->value = '';
            $old->save();
        }

        return response()->json([
            'message' => 'Tanda tangan Koordinator berhasil dihapus.'
        ]);
    }
}
