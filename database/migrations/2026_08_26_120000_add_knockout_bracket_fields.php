<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedTinyInteger('bracket_size')->nullable()->after('groups');
            $table->string('champion_name')->nullable()->after('status');
            $table->timestamp('completed_at')->nullable()->after('champion_name');
        });

        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->boolean('is_bracket')->default(false)->after('result');
            $table->string('bracket_code', 32)->nullable()->after('is_bracket');
            $table->string('round_code', 16)->nullable()->after('bracket_code');
            $table->unsignedInteger('bracket_order')->default(0)->after('round_code');

            $table->unsignedBigInteger('next_match_id')->nullable()->after('bracket_order');
            $table->string('next_slot', 1)->nullable()->after('next_match_id'); // A|B

            $table->unsignedBigInteger('source_match_a_id')->nullable()->after('next_slot');
            $table->unsignedBigInteger('source_match_b_id')->nullable()->after('source_match_a_id');

            $table->string('winner_name')->nullable()->after('source_match_b_id');
            $table->string('winner_side', 1)->nullable()->after('winner_name'); // A|B

            $table->foreign('next_match_id')->references('id')->on('tournament_matches')->nullOnDelete();
            $table->foreign('source_match_a_id')->references('id')->on('tournament_matches')->nullOnDelete();
            $table->foreign('source_match_b_id')->references('id')->on('tournament_matches')->nullOnDelete();

            $table->unique(['tournament_id', 'bracket_code']);
        });
    }

    public function down(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropUnique(['tournament_id', 'bracket_code']);
            $table->dropForeign(['next_match_id']);
            $table->dropForeign(['source_match_a_id']);
            $table->dropForeign(['source_match_b_id']);
            $table->dropColumn([
                'is_bracket',
                'bracket_code',
                'round_code',
                'bracket_order',
                'next_match_id',
                'next_slot',
                'source_match_a_id',
                'source_match_b_id',
                'winner_name',
                'winner_side',
            ]);
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['bracket_size', 'champion_name', 'completed_at']);
        });
    }
};
