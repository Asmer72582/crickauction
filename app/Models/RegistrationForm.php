<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class RegistrationForm extends Model
{
    protected $fillable = [
        'tournament_id',
        'auction_id',
        'name',
        'status',
        'deadline_at',
        'public_token',
        'current_version',
    ];

    protected function casts(): array
    {
        return [
            'deadline_at' => 'datetime',
            'current_version' => 'integer',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RegistrationFormVersion::class)->orderBy('version');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(PlayerRegistration::class);
    }

    public function auctions(): HasMany
    {
        return $this->hasMany(Auction::class);
    }

    public function currentVersionModel(): ?RegistrationFormVersion
    {
        return $this->versions()->where('version', $this->current_version)->first();
    }

    public function isOpen(): bool
    {
        if ($this->status !== 'open') {
            return false;
        }
        if (Schema::hasColumn('registration_forms', 'auction_id') && $this->auction_id === null) {
            return false;
        }
        if ($this->deadline_at && $this->deadline_at->isPast()) {
            return false;
        }

        return true;
    }

    public function publicUrl(): string
    {
        return url('/registration/'.$this->public_token);
    }
}
