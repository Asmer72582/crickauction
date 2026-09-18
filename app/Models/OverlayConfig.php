<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OverlayConfig extends Model
{
    protected $fillable = [
        'tournament_match_id',
        'theme_id',
        'active_panel',
        'public_token',
        'branding',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'branding' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'tournament_match_id');
    }

    public static function makeToken(): string
    {
        return Str::lower(Str::random(40));
    }

    public function publicUrl(array $query = []): string
    {
        $base = [
            'theme' => $this->theme_id,
            'panel' => $this->active_panel,
            'token' => $this->public_token,
        ];

        return url('/overlay/match/'.$this->tournament_match_id.'?'.http_build_query(array_merge($base, $query)));
    }

    public function regenerateToken(): void
    {
        $this->public_token = self::makeToken();
        $this->save();
    }
}
