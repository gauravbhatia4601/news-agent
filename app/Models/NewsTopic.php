<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NewsTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'category_id',
        'location_category_id',
        'topic_name',
        'topic_signature',
        'source_count',
        'generation_status',
        'llm_generated_at',
        'core_tokens',
        'retry_count',
    ];

    protected $casts = [
        'llm_generated_at' => 'datetime',
        'core_tokens' => 'array',
    ];

    public function categoryRelation(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function locationCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'location_category_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(NewsTopicSource::class, 'topic_id');
    }

    public function article(): HasOne
    {
        return $this->hasOne(NewsArticle::class, 'topic_id');
    }

    /**
     * Live stories that seeded or were updated by this topic (discovery provenance).
     */
    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'story_topics');
    }
}
