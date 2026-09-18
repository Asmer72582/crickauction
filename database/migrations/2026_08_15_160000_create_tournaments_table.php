<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sport')->default('Cricket');
            $table->string('type')->default('League Tournament');
            $table->string('theme_id')->default('midnight');
            $table->string('room_id')->unique();
            $table->unsignedTinyInteger('wickets')->default(10);
            $table->unsignedTinyInteger('groups')->default(1);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('status')->default('active'); // active, completed
            $table->string('assigned_to')->default('Operator');
            $table->unsignedInteger('theme_charge')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
