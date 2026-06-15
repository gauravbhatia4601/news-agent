<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NewsTopic extends Model
{
    protected $guarded = [];

    protected $casts = [
        'llm_generated_at' => 'datetime',
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
}
