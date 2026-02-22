<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsTopicSource extends Model
{
    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    /**
     * Get the topic that owns the source.
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(NewsTopic::class, 'topic_id');
    }
}