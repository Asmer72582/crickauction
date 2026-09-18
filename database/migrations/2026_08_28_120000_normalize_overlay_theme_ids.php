<?php

use App\Services\OverlayThemeRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $valid = OverlayThemeRegistry::ids();

        DB::table('tournaments')
            ->whereNotIn('theme_id', $valid)
            ->update(['theme_id' => OverlayThemeRegistry::OVERLAY_01]);
    }

    public function down(): void
    {
        // Irreversible — legacy flavour IDs were removed from the catalog.
    }
};
