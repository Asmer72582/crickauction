<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 20)->default('draft'); // draft, open, closed
            $table->timestamp('deadline_at')->nullable();
            $table->string('public_token', 64)->unique();
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamps();
        });

        Schema::create('registration_form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_form_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('schema');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['registration_form_id', 'version']);
        });

        Schema::create('player_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_form_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('form_version');
            $table->string('registration_code', 32)->unique();
            $table->json('data');
            $table->string('status', 24)->default('pending'); // pending, approved, rejected, changes_requested
            $table->text('review_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['registration_form_id', 'status']);
        });

        Schema::create('registration_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_registration_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->string('disk_path');
            $table->string('original_name');
            $table->string('mime', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_form_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 20)->default('draft'); // draft, published, live, paused, completed
            $table->string('room_id', 80)->unique();
            $table->string('public_token', 64)->unique();
            $table->json('rules')->nullable();
            $table->json('live_state')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->foreignId('current_auction_player_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('auction_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('short_name', 12)->nullable();
            $table->string('logo')->nullable();
            $table->string('owner')->nullable();
            $table->string('manager')->nullable();
            $table->unsignedBigInteger('starting_purse')->default(0);
            $table->unsignedBigInteger('spent_purse')->default(0);
            $table->unsignedTinyInteger('min_squad')->default(11);
            $table->unsignedTinyInteger('max_squad')->default(25);
            $table->json('restrictions')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('auction_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_registration_id')->constrained()->cascadeOnDelete();
            $table->string('category')->nullable();
            $table->string('set_name')->nullable();
            $table->unsignedBigInteger('base_price')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('pool'); // pool, current, sold, unsold
            $table->unsignedBigInteger('sold_price')->nullable();
            $table->foreignId('auction_team_id')->nullable()->constrained('auction_teams')->nullOnDelete();
            $table->timestamps();
            $table->unique(['auction_id', 'player_registration_id']);
        });

        Schema::create('auction_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auction_team_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();
            $table->index(['auction_player_id', 'created_at']);
        });

        Schema::create('auction_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['auction_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_events');
        Schema::dropIfExists('auction_bids');
        Schema::dropIfExists('auction_players');
        Schema::dropIfExists('auction_teams');
        Schema::dropIfExists('auctions');
        Schema::dropIfExists('registration_files');
        Schema::dropIfExists('player_registrations');
        Schema::dropIfExists('registration_form_versions');
        Schema::dropIfExists('registration_forms');
    }
};
