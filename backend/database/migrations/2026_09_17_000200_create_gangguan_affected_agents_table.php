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
        Schema::create('gangguan_affected_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gangguan_id')->constrained('gangguans')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('agent_name');
            $table->string('cubicle_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['gangguan_id', 'user_id'], 'unique_gangguan_affected_agent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gangguan_affected_agents');
    }
};
