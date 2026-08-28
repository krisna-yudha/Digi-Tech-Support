<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gangguans', function (Blueprint $table) {
            $table->enum('jenis_gangguan', ['Personal', 'Massal'])->default('Personal')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gangguans', function (Blueprint $table) {
            $table->dropColumn('jenis_gangguan');
        });
    }
};
