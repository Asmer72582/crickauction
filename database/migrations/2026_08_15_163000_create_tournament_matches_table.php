<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->unsignedInteger('match_no')->default(1);
            $table->string('round')->default('League');
            $table->unsignedTinyInteger('overs')->default(20);
            $table->dateTime('scheduled_at')->nullable();
            $table->string('team_a_name')->default('Team A');
            $table->string('team_b_name')->default('Team B');
            $table->string('room_id')->unique();
            $table->string('status')->default('scheduled'); // scheduled, live, completed
            $table->string('result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_matches');
    }
};
