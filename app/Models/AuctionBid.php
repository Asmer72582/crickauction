<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionBid extends Model
{
    protected $fillable = [
        'auction_player_id',
        'auction_team_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function auctionPlayer(): BelongsTo
    {
        return $this->belongsTo(AuctionPlayer::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(AuctionTeam::class, 'auction_team_id');
    }
}
