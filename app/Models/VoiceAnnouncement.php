<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'audio_path', 'duration_seconds', 'created_by', 'started_at', 'is_playing', 'order_id', 'message', 'expires_at'])]
class VoiceAnnouncement extends Model
{
    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'started_at' => 'datetime',
            'is_playing' => 'boolean',
            'order_id' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
