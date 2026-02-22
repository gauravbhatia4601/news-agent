<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NewsTopic extends Model
{
    protected $guarded = [];

    protected $casts = [
        'llm_generated_at' => 'datetime',
    ];

    /**
     * Get the sources associated with the topic.
     */
    public function sources(): HasMany
    {
        return $this->hasMany(NewsTopicSource::class, 'topic_id');
    }

    /**
     * Get the article generated for the topic.
     */
    public function article(): HasOne
    {
        return $this->hasOne(NewsArticle::class, 'topic_id');
    }
}