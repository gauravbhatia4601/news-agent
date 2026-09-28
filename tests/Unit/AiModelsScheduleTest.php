<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class AiModelsScheduleTest extends TestCase
{
    /**
     * The sync is scheduled hourly at :50 — off the discovery/live-story marks
     * (:10, :30, :40) and before the top-of-hour discovery run.
     */
    public function test_ai_sync_models_is_scheduled_hourly(): void
    {
        $events = collect(Schedule::events());

        $syncEvent = $events->first(fn ($event) => str_contains((string) $event->command, 'ai:sync-models'));

        $this->assertNotNull($syncEvent, 'ai:sync-models must be scheduled');
        $this->assertSame('50', explode(' ', $syncEvent->expression)[0], 'hourlyAt(50) — off the busy minutes');
    }
}
