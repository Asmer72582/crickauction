<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlayerRegistration extends Model
{
    protected $fillable = [
        'registration_form_id',
        'form_version',
        'registration_code',
        'data',
        'status',
        'review_notes',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'form_version' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(RegistrationForm::class, 'registration_form_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(RegistrationFile::class);
    }

    public function auctionPlayers(): HasMany
    {
        return $this->hasMany(AuctionPlayer::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function displayName(): string
    {
        $data = $this->data ?? [];

        return (string) ($data['full_name'] ?? $data['name'] ?? 'Player #'.$this->id);
    }

    public function displayRole(): string
    {
        $data = $this->data ?? [];

        return (string) ($data['playing_role'] ?? $data['role'] ?? '—');
    }

    /** @return list<string> */
    public function rolesList(): array
    {
        $role = $this->displayRole();
        if ($role === '' || $role === '—') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $role))));
    }

    public function statValue(string $key, mixed $default = null): mixed
    {
        $data = $this->data ?? [];

        return $data[$key] ?? $default;
    }

    public function hasStats(): bool
    {
        foreach (['matches', 'runs', 'highest_score', 'wickets'] as $key) {
            if ($this->statValue($key) !== null && $this->statValue($key) !== '') {
                return true;
            }
        }

        return false;
    }

    public function photoUrl(): ?string
    {
        $file = $this->files()->where('field_key', 'profile_photo')->first()
            ?? $this->files()->where('field_key', 'like', '%photo%')->first();

        if ($file) {
            return '/storage/'.$file->disk_path;
        }

        $data = $this->data ?? [];
        $url = $data['profile_photo'] ?? $data['photo'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }
}
