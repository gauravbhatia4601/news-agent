<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryFreshnessMapTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Each urgency state maps to the correct per-source freshness windows.
     */
    public function test_freshness_config_per_urgency(): void
    {
        $live = Story::factory()->live()->create();
        $config = $live->freshnessConfig();
        $this->assertSame('1h', $config['google']);
        $this->assertSame('ph', $config['brave']);
        $this->assertSame('15min', $config['gdelt']);
        $this->assertSame(1, $config['hours']);

        $developing = Story::factory()->create();
        $config = $developing->freshnessConfig();
        $this->assertSame('6h', $config['google']);
        $this->assertSame('pd', $config['brave']);
        $this->assertSame('6h', $config['gdelt']);
        $this->assertSame(6, $config['hours']);

        $ongoing = Story::factory()->create(['urgency' => 'ongoing']);
        $config = $ongoing->freshnessConfig();
        $this->assertSame('1d', $config['google']);
        $this->assertSame('pd', $config['brave']);
        $this->assertSame('24h', $config['gdelt']);
        $this->assertSame(24, $config['hours']);

        $concluded = Story::factory()->concluded()->create();
        $config = $concluded->freshnessConfig();
        $this->assertSame('1d', $config['google']);
        $this->assertSame('pd', $config['brave']);
        $this->assertSame('24h', $config['gdelt']);
        $this->assertSame(24, $config['hours']);
    }

    public function test_is_live_and_is_concluded_helpers(): void
    {
        $live = Story::factory()->live()->create();
        $this->assertTrue($live->isLive());
        $this->assertFalse($live->isConcluded());

        $concluded = Story::factory()->concluded()->create();
        $this->assertFalse($concluded->isLive());
        $this->assertTrue($concluded->isConcluded());
    }

    public function test_scope_active_excludes_concluded(): void
    {
        Story::factory()->live()->create();
        Story::factory()->concluded()->create();
        Story::factory()->create();

        $active = Story::active()->count();
        $this->assertSame(2, $active);
    }
}
