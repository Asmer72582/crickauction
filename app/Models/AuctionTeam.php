<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuctionTeam extends Model
{
    protected $fillable = [
        'auction_id',
        'name',
        'short_name',
        'logo',
        'owner',
        'city',
        'manager',
        'starting_purse',
        'spent_purse',
        'min_squad',
        'max_squad',
        'restrictions',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'restrictions' => 'array',
            'starting_purse' => 'integer',
            'spent_purse' => 'integer',
        ];
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function squad(): HasMany
    {
        return $this->hasMany(AuctionPlayer::class)->where('status', 'sold');
    }

    public function remainingPurse(): int
    {
        return max(0, (int) $this->starting_purse - (int) $this->spent_purse);
    }
}
