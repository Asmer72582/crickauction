<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuctionPlayer extends Model
{
    protected $fillable = [
        'auction_id',
        'player_registration_id',
        'category',
        'set_name',
        'base_price',
        'sort_order',
        'status',
        'sold_price',
        'auction_team_id',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'sold_price' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(PlayerRegistration::class, 'player_registration_id');
    }

    public function soldTeam(): BelongsTo
    {
        return $this->belongsTo(AuctionTeam::class, 'auction_team_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(AuctionBid::class)->orderByDesc('created_at');
    }
}
