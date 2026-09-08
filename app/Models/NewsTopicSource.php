<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsTopicSource extends Model
{
    protected $fillable = [
        'topic_id',
        'source_name',
        'source_url',
        'source_url_hash',
        'headline',
        'summary',
        'published_at',
    ];

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
