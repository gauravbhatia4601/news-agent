<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueueJobLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection',
        'queue',
        'job_uuid',
        'job_class',
        'job_signature',
        'status',
        'attempts',
        'exception',
        'payload',
        'started_at',
        'finished_at',
        'duration_seconds',
    ];

    protected $casts = [
        'payload' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
