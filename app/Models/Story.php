<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Story extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'description',
        'search_query',
        'category_id',
        'urgency',
        'status',
        'started_at',
        'concluded_at',
        'last_monitored_at',
        'monitor_interval_minutes',
        'empty_cycles',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'concluded_at' => 'datetime',
        'last_monitored_at' => 'datetime',
    ];

    /**
     * Articles linked to this story (the timeline).
     */
    public function latestArticle()
    {
        return $this->hasOne(\App\Models\NewsArticle::class, 'story_id')
            ->where('status', 'published')
            ->orderByDesc('published_at');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(NewsArticle::class, 'story_id');
    }

    /**
     * Discovery-provenance pivot — which topics seeded/updated this story.
     */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(NewsTopic::class, 'story_topics');
    }

    /**
     * Optional category for the story.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Scope to non-concluded stories (active + monitorable).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('urgency', '!=', 'concluded');
    }

    /**
     * Per-urgency freshness config: google/brave/gdelt windows + fresh hours.
     *
     * @return array{google: string, brave: string, gdelt: string, hours: int}
     */
    public function freshnessConfig(): array
    {
        $map = config('news-engine.live_stories.urgency_freshness_map', []);

        return $map[$this->urgency] ?? $map['developing'] ?? [
            'google' => '6h',
            'brave' => 'pd',
            'gdelt' => '6h',
            'hours' => 6,
        ];
    }

    public function isLive(): bool
    {
        return $this->urgency === 'live';
    }

    public function isConcluded(): bool
    {
        return $this->urgency === 'concluded';
    }
}
