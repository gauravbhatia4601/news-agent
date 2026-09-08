<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'topic_id',
        'title',
        'content',
        'provider',
        'model',
        'metadata',
        'slug',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'image_url',
        'thumbnail_url',
        'status',
        'quality_report',
        'generation_duration_seconds',
        'views',
        'hot_score',
        'view_velocity',
        'views_1h',
        'views_6h',
        'views_24h',
        'momentum_score',
        'published_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'quality_report' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * Get the topic that the article belongs to.
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(NewsTopic::class, 'topic_id');
    }
}
