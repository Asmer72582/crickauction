<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationFile extends Model
{
    protected $fillable = [
        'player_registration_id',
        'field_key',
        'disk_path',
        'original_name',
        'mime',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(PlayerRegistration::class, 'player_registration_id');
    }
}
