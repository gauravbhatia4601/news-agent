<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInvocation extends Model
{
    protected $fillable = [
        'topic_id',
        'provider',
        'model',
        'invocation_id',
        'prompt_tokens',
        'completion_tokens',
        'cache_write_tokens',
        'cache_read_tokens',
        'reasoning_tokens',
        'total_tokens',
        'duration_ms',
        'status',
        'error',
        'invoked_at',
    ];

    protected $casts = [
        'invoked_at' => 'datetime',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(NewsTopic::class, 'topic_id');
    }
}
