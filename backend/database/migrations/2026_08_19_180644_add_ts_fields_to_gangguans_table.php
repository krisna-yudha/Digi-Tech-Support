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
            $table->text('penyebab_permasalahan')->nullable();
            $table->text('penyelesaian_masalah')->nullable();
            $table->text('impact')->nullable();
            $table->text('analisa')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gangguans', function (Blueprint $table) {
            $table->dropColumn([
                'penyebab_permasalahan',
                'penyelesaian_masalah',
                'impact',
                'analisa'
            ]);
        });
    }
};
