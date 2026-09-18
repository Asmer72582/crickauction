<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedTeam extends Model
{
    protected $table = 'saved_teams';

    protected $fillable = [
        'name',
        'name_key',
        'short_name',
        'primary_color',
        'secondary_color',
        'logo',
        'players',
        'tournament_id',
    ];

    protected function casts(): array
    {
        return [
            'players' => 'array',
            'tournament_id' => 'integer',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public static function makeKey(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? ''));
    }

    public function toLibraryArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'shortName' => $this->short_name,
            'primaryColor' => $this->primary_color,
            'secondaryColor' => $this->secondary_color,
            'logo' => $this->logo,
            'players' => array_values($this->players ?? []),
            'playerCount' => count($this->players ?? []),
            'updatedAt' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
