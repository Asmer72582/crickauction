<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchRoom extends Model
{
    protected $fillable = [
        'room_id',
        'state',
        'event_queue',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'event_queue' => 'array',
            'version' => 'integer',
        ];
    }
}
