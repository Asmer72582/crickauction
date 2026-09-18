<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Auction extends Model
{
    protected $fillable = [
        'tournament_id',
        'registration_form_id',
        'name',
        'status',
        'room_id',
        'public_token',
        'rules',
        'live_state',
        'version',
        'current_auction_player_id',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'live_state' => 'array',
            'version' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function registrationForm(): BelongsTo
    {
        return $this->belongsTo(RegistrationForm::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(AuctionTeam::class)->orderBy('sort_order');
    }

    public function players(): HasMany
    {
        return $this->hasMany(AuctionPlayer::class)->orderBy('sort_order');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AuctionEvent::class)->orderByDesc('created_at');
    }

    public function currentPlayer(): BelongsTo
    {
        return $this->belongsTo(AuctionPlayer::class, 'current_auction_player_id');
    }

    public function remainingPurse(AuctionTeam $team): int
    {
        return max(0, (int) $team->starting_purse - (int) $team->spent_purse);
    }

    public function workflowPhase(): string
    {
        return match ($this->status) {
            'live', 'paused' => 'live',
            'completed' => 'completed',
            'published' => 'ready',
            default => 'verify',
        };
    }

    public function phaseLabel(): string
    {
        return match ($this->workflowPhase()) {
            'verify' => 'Verification',
            'ready' => 'Ready to start',
            'live' => 'Live',
            'completed' => 'Completed',
            default => 'Setup',
        };
    }

    public function isStandalone(): bool
    {
        return $this->tournament?->isStandaloneHost() ?? false;
    }

    public function displayTournamentName(): string
    {
        if ($this->isStandalone()) {
            return 'Standalone';
        }

        return $this->tournament?->name ?? '—';
    }

    public function overlayUrl(): string
    {
        return url('/overlay/auction/'.$this->id.'?token='.$this->public_token);
    }

    public function registrationUrl(): ?string
    {
        $token = $this->registrationForm?->public_token;

        return $token ? url('/registration/'.$token) : null;
    }

    public function ownerPortalUrl(): string
    {
        return url('/owners/auction/'.$this->id.'?token='.$this->public_token);
    }
}
