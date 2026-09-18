<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overlay_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_match_id')->constrained('tournament_matches')->cascadeOnDelete();
            $table->string('theme_id')->default('modern');
            $table->string('active_panel')->default('score'); // score|batsmen|bowler|last_ball|last_over|partnership|fow|match_info|player_card|full
            $table->string('public_token', 64)->unique();
            $table->json('branding')->nullable(); // tournament_logo, sponsor_logo, sponsor_text, powered_by
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique('tournament_match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overlay_configs');
    }
};
