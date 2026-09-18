<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tournament extends Model
{
    public const STANDALONE_SLUG = 'standalone-auctions';

    protected $fillable = [
        'name',
        'slug',
        'sport',
        'type',
        'theme_id',
        'room_id',
        'wickets',
        'groups',
        'bracket_size',
        'starts_at',
        'ends_at',
        'status',
        'champion_name',
        'completed_at',
        'assigned_to',
        'theme_charge',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'completed_at' => 'datetime',
            'wickets' => 'integer',
            'groups' => 'integer',
            'bracket_size' => 'integer',
            'theme_charge' => 'integer',
        ];
    }

    public function isKnockout(): bool
    {
        if ((int) ($this->bracket_size ?? 0) === 16) {
            return true;
        }

        return stripos((string) $this->type, 'knockout') !== false;
    }

    public function isStandaloneHost(): bool
    {
        return $this->slug === self::STANDALONE_SLUG;
    }

    public function scopeVisible($query)
    {
        return $query->where('slug', '!=', self::STANDALONE_SLUG);
    }

    public static function makeRoomId(string $name): string
    {
        $base = Str::slug($name) ?: 'match';
        $base = Str::limit($base, 40, '');
        $suffix = Str::lower(Str::random(4));

        return $base.'-'.$suffix;
    }

    public function theme(): array
    {
        return \App\Services\OverlayThemeRegistry::get($this->theme_id);
    }

    public function matches()
    {
        return $this->hasMany(TournamentMatch::class)->orderBy('match_no');
    }

    public function registrationForms()
    {
        return $this->hasMany(RegistrationForm::class);
    }

    public function auctions()
    {
        return $this->hasMany(Auction::class)->latest();
    }
}
