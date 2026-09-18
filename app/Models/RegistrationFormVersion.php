<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationFormVersion extends Model
{
    protected $fillable = [
        'registration_form_id',
        'version',
        'schema',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'published_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(RegistrationForm::class, 'registration_form_id');
    }
}
