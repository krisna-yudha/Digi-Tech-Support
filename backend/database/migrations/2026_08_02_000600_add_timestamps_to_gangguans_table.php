<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gangguans', function (Blueprint $table) {
            // Tgl Read: waktu TS pertama kali membuka / membaca tiket
            $table->timestamp('read_at')->nullable()->after('end_time');
            // read_by: siapa TS yang membaca pertama kali
            $table->foreignId('read_by')->nullable()->constrained('users')->nullOnDelete()->after('read_at');

            // Tgl Selesai: waktu TS menandai tiket sebagai closed
            $table->timestamp('resolved_at')->nullable()->after('read_by');
            // resolved_by: siapa TS yang menyelesaikan
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('gangguans', function (Blueprint $table) {
            $table->dropForeign(['read_by']);
            $table->dropForeign(['resolved_by']);
            $table->dropColumn(['read_at', 'read_by', 'resolved_at', 'resolved_by']);
        });
    }
};
