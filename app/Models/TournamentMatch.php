<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TournamentMatch extends Model
{
    protected $table = 'tournament_matches';

    protected $fillable = [
        'tournament_id',
        'match_no',
        'round',
        'overs',
        'scheduled_at',
        'team_a_name',
        'team_b_name',
        'room_id',
        'status',
        'result',
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
    ];

    protected function casts(): array
    {
        return [
            'match_no' => 'integer',
            'overs' => 'integer',
            'scheduled_at' => 'datetime',
            'is_bracket' => 'boolean',
            'bracket_order' => 'integer',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function nextMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_match_id');
    }

    public function sourceMatchA(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_match_a_id');
    }

    public function sourceMatchB(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_match_b_id');
    }

    public function overlayConfig()
    {
        return $this->hasOne(\App\Models\OverlayConfig::class, 'tournament_match_id');
    }

    public static function makeRoomId(int $tournamentId, int $matchNo): string
    {
        return 't'.$tournamentId.'-m'.$matchNo.'-'.Str::lower(Str::random(4));
    }

    public function overlayUrl(): string
    {
        return url('/overlay?room='.$this->room_id);
    }

    public function controlUrl(): string
    {
        return url('/control?room='.$this->room_id);
    }

    public function isWaiting(): bool
    {
        return $this->status === 'waiting';
    }

    public function isReady(): bool
    {
        return in_array($this->status, ['ready', 'scheduled'], true);
    }

    public function canOpenForScoring(): bool
    {
        return ! $this->isWaiting() && $this->status !== 'completed';
    }

    /**
     * Label for Team A — real name or "Winner R16-1" placeholder.
     */
    public function displayTeamA(): string
    {
        $name = trim((string) ($this->team_a_name ?? ''));
        if ($name !== '' && ! $this->isPlaceholderLabel($name)) {
            return $name;
        }

        return $this->placeholderFromSource($this->source_match_a_id, 'A');
    }

    public function displayTeamB(): string
    {
        $name = trim((string) ($this->team_b_name ?? ''));
        if ($name !== '' && ! $this->isPlaceholderLabel($name)) {
            return $name;
        }

        return $this->placeholderFromSource($this->source_match_b_id, 'B');
    }

    protected function isPlaceholderLabel(string $name): bool
    {
        $n = strtolower(trim($name));

        return $n === '' || $n === 'tbd' || str_starts_with($n, 'winner ');
    }

    protected function placeholderFromSource(?int $sourceId, string $fallbackSlot): string
    {
        if (! $sourceId) {
            return 'TBD';
        }

        $relation = $fallbackSlot === 'B' ? 'sourceMatchB' : 'sourceMatchA';
        $source = $this->relationLoaded($relation)
            ? $this->{$relation}
            : self::query()->find($sourceId);

        if ($source && $source->bracket_code) {
            return 'Winner '.$source->bracket_code;
        }

        return 'TBD';
    }
}
