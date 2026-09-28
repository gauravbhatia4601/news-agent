<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModel extends Model
{
    protected $fillable = [
        'ai_model_provider_id',
        'feed_id',
        'source',
        'name',
        'context_length',
        'input_price_per_million',
        'output_price_per_million',
        'modality',
        'variant',
        'is_active',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'context_length' => 'integer',
            'input_price_per_million' => 'decimal:6',
            'output_price_per_million' => 'decimal:6',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiModelProvider::class, 'ai_model_provider_id');
    }
}
