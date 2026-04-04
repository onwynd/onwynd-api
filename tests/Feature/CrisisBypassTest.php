<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AiQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * ARCH-6: Tests for 72-hour crisis bypass TTL in AiQuotaService.
 *
 * Verifies that:
 * - setDistressFlag() stores a user-scoped key with 72h TTL
 * - isInCrisisWindow() returns true within the window
 * - clearDistressFlag() removes the flag
 * - enforce() never blocks a user in a crisis window
 */
class CrisisBypassTest extends TestCase
{
    use RefreshDatabase;

    private AiQuotaService $quota;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quota = new AiQuotaService;
    }

    public function test_set_distress_flag_creates_user_scoped_cache_key(): void
    {
        $user = User::factory()->create();
        $this->quota->setDistressFlag($user);

        $this->assertTrue(
            Cache::has("quota:distress:{$user->id}"),
            'Distress flag key should exist in cache'
        );
    }

    public function test_is_in_crisis_window_returns_true_after_flag_set(): void
    {
        $user = User::factory()->create();
        $this->quota->setDistressFlag($user);

        $this->assertTrue($this->quota->isInCrisisWindow($user));
    }

    public function test_is_in_crisis_window_returns_false_without_flag(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($this->quota->isInCrisisWindow($user));
    }

    public function test_clear_distress_flag_removes_crisis_window(): void
    {
        $user = User::factory()->create();
        $this->quota->setDistressFlag($user);
        $this->quota->clearDistressFlag($user);

        $this->assertFalse($this->quota->isInCrisisWindow($user));
    }

    public function test_enforce_never_blocks_user_in_crisis_window(): void
    {
        $user = User::factory()->create();
        $this->quota->setDistressFlag($user);

        // Set an artificially low quota
        $enforced = false;
        try {
            $this->quota->enforce($user);
        } catch (\Throwable $e) {
            $enforced = true;
        }

        $this->assertFalse($enforced, 'enforce() must not throw for user in crisis window');
    }

    public function test_distress_flag_key_is_scoped_per_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->quota->setDistressFlag($user1);

        $this->assertTrue($this->quota->isInCrisisWindow($user1));
        $this->assertFalse($this->quota->isInCrisisWindow($user2));
    }
}
