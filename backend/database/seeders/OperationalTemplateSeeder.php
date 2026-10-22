<?php

namespace Database\Seeders;

use App\Models\OperationalTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class OperationalTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();

        $templates = [
            [
                'title' => 'Format Eskalasi Kendala Teknis Jaringan',
                'category' => 'Teknis',
                'content' => '<h2>Format Eskalasi Teknis</h2><p><strong>ID Pelanggan:</strong> [Isi ID Pelanggan]</p><p><strong>Nama Pelanggan:</strong> [Isi Nama]</p><p><strong>Nomor Kontak:</strong> [Isi Nomor HP/WA]</p><p><strong>Status Redaman/LOS:</strong> [Isi Redaman atau Status Lampu Indikator]</p><p><strong>Kronologi & Langkah Troubleshooting yang Sudah Dilakukan:</strong></p><ul><li>Restart ONT/Router: Ya / Tidak</li><li>Pengecekan Kabel Fiber: Normal / Lepas / Tertekuk</li><li>Hasil Ping Gateway/DNS: RTO / Request Timed Out</li></ul><p><strong>Catatan Tambahan untuk Tim TS:</strong></p><blockquote>Mohon bantu eskalasi segera karena pelanggan membutuhkan koneksi untuk operasional kantor.</blockquote>',
                'is_shared' => true,
            ],
            [
                'title' => 'Script Respon Pembuka & Konfirmasi Pelanggan',
                'category' => 'Script Respon',
                'content' => '<h2>Script Pembuka Panggilan / Chat</h2><p>Selamat pagi/siang/sore Bapak/Ibu, terima kasih telah menghubungi Layanan Dukungan Teknis IS Call Center. Dengan saya <code>[Nama Agent]</code>, ada yang bisa saya bantu?</p><hr/><p><strong>Konfirmasi Data:</strong></p><ul><li>Bisa dibantu dengan nomor ID Pelanggan atau nomor tiket sebelumnya?</li><li>Apakah kendala terjadi pada seluruh perangkat atau hanya satu perangkat tertentu?</li></ul>',
                'is_shared' => true,
            ],
            [
                'title' => 'Template Informasi Gangguan Massal Wilayah',
                'category' => 'Eskalasi',
                'content' => '<h2>Pemberitahuan Insiden Massal</h2><p>Bapak/Ibu yang kami hormati, saat ini sedang terjadi <strong>Gangguan Massal</strong> pada jalur distribusi wilayah <code>[Nama Wilayah/Area]</code>.</p><p>Tim teknis lapangan kami saat ini sedang berada di lokasi untuk melakukan penanganan dan perbaikan sesegera mungkin.</p><p>Estimasi waktu pemulihan: <strong>[Estimasi Jam]</strong>.</p><p>Mohon maaf atas ketidaknyamanan yang terjadi. Kami akan terus memperbarui status progres melalui tiket laporan ini.</p>',
                'is_shared' => true,
            ],
        ];

        foreach ($templates as $t) {
            OperationalTemplate::firstOrCreate(
                ['title' => $t['title']],
                [
                    'user_id' => $admin?->id,
                    'category' => $t['category'],
                    'content' => $t['content'],
                    'is_shared' => $t['is_shared'],
                ]
            );
        }
    }
}
