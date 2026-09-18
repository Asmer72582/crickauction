<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_teams', function (Blueprint $table) {
            $table->string('city')->nullable()->after('owner');
        });
    }

    public function down(): void
    {
        Schema::table('auction_teams', function (Blueprint $table) {
            $table->dropColumn('city');
        });
    }
};
