<?php

namespace App\Listeners;

use App\Models\QueueJobLog;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\Date;

class LogQueueJobs
{
    public function handle(JobQueued|JobProcessing|JobProcessed|JobFailed|JobExceptionOccurred|JobReleasedAfterException $event): void
    {
        match (true) {
            $event instanceof JobQueued => $this->logJobQueued($event),
            $event instanceof JobProcessing => $this->logJobProcessing($event),
            $event instanceof JobProcessed => $this->logJobProcessed($event),
            $event instanceof JobFailed => $this->logJobFailed($event),
            $event instanceof JobExceptionOccurred => $this->logJobException($event),
            $event instanceof JobReleasedAfterException => $this->logJobReleased($event),
        };
    }

    private function logJobQueued(JobQueued $event): void
    {
        $decoded = $event->payload();
        $payload = $this->resolvePayloadFromArray($decoded);
        $uuid = $decoded['uuid'] ?? null;

        $data = [
            'connection' => $event->connectionName,
            'queue' => $event->queue ?? 'default',
            'job_uuid' => $uuid,
            'job_class' => $payload['class'] ?? null,
            'job_signature' => $payload['signature'] ?? null,
            'status' => 'pending',
            'attempts' => 0,
            'payload' => $payload,
        ];

        if (! $uuid) {
            QueueJobLog::create($data);
            return;
        }

        try {
            QueueJobLog::updateOrCreate(
                ['connection' => $event->connectionName, 'queue' => $event->queue ?? 'default', 'job_uuid' => $uuid],
                $data,
            );
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Another listener already recorded this job; ignore.
        }
    }

    private function logJobProcessing(JobProcessing $event): void
    {
        $raw = $event->job?->payload();
        $decoded = is_string($raw) ? json_decode($raw, true) : [];
        $decoded = is_array($decoded) ? $decoded : [];
        $payload = $this->resolvePayloadFromArray($decoded);

        $this->upsert(
            connection: $event->connectionName,
            queue: $event->job?->getQueue() ?? 'default',
            uuid: $decoded['uuid'] ?? null,
            updates: [
                'job_class' => $payload['class'] ?? null,
                'job_signature' => $payload['signature'] ?? null,
                'status' => 'processing',
                'attempts' => $event->job?->attempts() ?? 0,
                'payload' => $payload,
                'started_at' => Date::now(),
                'finished_at' => null,
                'duration_seconds' => null,
            ],
        );
    }

    private function logJobProcessed(JobProcessed $event): void
    {
        $raw = $event->job?->payload();
        $decoded = is_string($raw) ? json_decode($raw, true) : [];
        $decoded = is_array($decoded) ? $decoded : [];
        $payload = $this->resolvePayloadFromArray($decoded);
        $log = $this->findLog($event->connectionName, $event->job?->getQueue() ?? 'default', $decoded['uuid'] ?? null);

        $startedAt = $log?->started_at ?? Date::now();
        $finishedAt = Date::now();

        $this->upsert(
            connection: $event->connectionName,
            queue: $event->job?->getQueue() ?? 'default',
            uuid: $decoded['uuid'] ?? null,
            updates: [
                'job_class' => $payload['class'] ?? null,
                'job_signature' => $payload['signature'] ?? null,
                'status' => 'processed',
                'attempts' => $event->job?->attempts() ?? 0,
                'payload' => $payload,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'duration_seconds' => (int) $finishedAt->diffInSeconds($startedAt),
            ],
        );
    }

    private function logJobFailed(JobFailed $event): void
    {
        $raw = $event->job?->payload();
        $decoded = is_string($raw) ? json_decode($raw, true) : [];
        $decoded = is_array($decoded) ? $decoded : [];
        $payload = $this->resolvePayloadFromArray($decoded);
        $log = $this->findLog($event->connectionName, $event->job?->getQueue() ?? 'default', $decoded['uuid'] ?? null);

        $startedAt = $log?->started_at ?? Date::now();
        $finishedAt = Date::now();

        $this->upsert(
            connection: $event->connectionName,
            queue: $event->job?->getQueue() ?? 'default',
            uuid: $decoded['uuid'] ?? null,
            updates: [
                'job_class' => $payload['class'] ?? null,
                'job_signature' => $payload['signature'] ?? null,
                'status' => 'failed',
                'attempts' => $event->job?->attempts() ?? 0,
                'exception' => $this->serializeException($event->exception),
                'payload' => $payload,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'duration_seconds' => (int) $finishedAt->diffInSeconds($startedAt),
            ],
        );
    }

    private function logJobException(JobExceptionOccurred $event): void
    {
        $raw = $event->job?->payload();
        $decoded = is_string($raw) ? json_decode($raw, true) : [];
        $decoded = is_array($decoded) ? $decoded : [];
        $payload = $this->resolvePayloadFromArray($decoded);

        $this->upsert(
            connection: $event->connectionName,
            queue: $event->job?->getQueue() ?? 'default',
            uuid: $decoded['uuid'] ?? null,
            updates: [
                'status' => 'processing',
                'attempts' => $event->job?->attempts() ?? 0,
                'exception' => $this->serializeException($event->exception),
                'payload' => $payload,
            ],
        );
    }

    private function logJobReleased(JobReleasedAfterException $event): void
    {
        $raw = $event->job?->payload();
        $decoded = is_string($raw) ? json_decode($raw, true) : [];
        $decoded = is_array($decoded) ? $decoded : [];
        $payload = $this->resolvePayloadFromArray($decoded);

        $this->upsert(
            connection: $event->connectionName,
            queue: $event->job?->getQueue() ?? 'default',
            uuid: $decoded['uuid'] ?? null,
            updates: [
                'status' => 'released',
                'attempts' => $event->job?->attempts() ?? 0,
                'exception' => $event->exception ? $this->serializeException($event->exception) : null,
                'payload' => $payload,
            ],
        );
    }

    private function resolvePayload(Job|string $job): array
    {
        $raw = null;

        if ($job instanceof Job) {
            $raw = $job->payload();
        } elseif (is_string($job)) {
            $raw = $job;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : [];
        if (! is_array($decoded)) {
            $decoded = [];
        }

        return $this->resolvePayloadFromArray($decoded);
    }

    private function resolvePayloadFromArray(array $decoded): array
    {
        $command = $decoded['data']['command'] ?? null;
        $signature = null;
        $class = $decoded['displayName'] ?? ($decoded['data']['commandName'] ?? null);

        if (is_string($command)) {
            $unserialized = @unserialize($command);
            if ($unserialized instanceof \App\Jobs\GenerateArticle) {
                $signature = $unserialized->topicSignature;
                $class = get_class($unserialized);
            } elseif (is_object($unserialized)) {
                $class = get_class($unserialized);
            }
        }

        return [
            'uuid' => $decoded['uuid'] ?? null,
            'class' => $class,
            'signature' => $signature,
            'displayName' => $decoded['displayName'] ?? null,
            'commandName' => $decoded['data']['commandName'] ?? null,
            'attempts' => $decoded['attempts'] ?? 0,
        ];
    }

    private function upsert(string $connection, string $queue, ?string $uuid, array $updates): void
    {
        if (! $uuid) {
            QueueJobLog::create($updates + [
                'connection' => $connection,
                'queue' => $queue,
                'job_uuid' => $uuid,
            ]);
            return;
        }

        QueueJobLog::updateOrCreate(
            [
                'connection' => $connection,
                'queue' => $queue,
                'job_uuid' => $uuid,
            ],
            $updates,
        );
    }

    private function findLog(string $connection, string $queue, ?string $uuid): ?QueueJobLog
    {
        if (! $uuid) {
            return null;
        }

        return QueueJobLog::where('connection', $connection)
            ->where('queue', $queue)
            ->where('job_uuid', $uuid)
            ->first();
    }

    private function serializeException(?\Throwable $e): ?string
    {
        if (! $e instanceof \Throwable) {
            return null;
        }

        return get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString();
    }
}
