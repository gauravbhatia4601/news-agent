<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryUpdate extends Model
{
    protected $fillable = [
        'story_id',
        'content',
        'event_at',
        'source_name',
        'source_url',
    ];

    protected $casts = [
        'event_at' => 'datetime',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
