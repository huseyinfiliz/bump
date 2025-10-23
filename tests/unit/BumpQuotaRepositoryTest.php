<?php

namespace HuseyinFiliz\Bump\Tests\Unit;

use Flarum\Testing\unit\TestCase;
use HuseyinFiliz\Bump\Repository\BumpQuotaRepository;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Mockery as m;

class BumpQuotaRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        m::close();
    }

    /**
     * Test that BumpQuotaRepository can be instantiated.
     */
    public function test_can_instantiate_repository()
    {
        $cache = m::mock(CacheRepository::class);
        $settings = m::mock(SettingsRepositoryInterface::class);

        $repository = new BumpQuotaRepository($cache, $settings);

        $this->assertInstanceOf(BumpQuotaRepository::class, $repository);
    }

    /**
     * Test that cache TTL constants are defined correctly.
     */
    public function test_cache_ttl_constants()
    {
        $this->assertEquals(60, BumpQuotaRepository::QUOTA_CACHE_TTL);
        $this->assertEquals(300, BumpQuotaRepository::STATS_CACHE_TTL);
    }

    /**
     * Test that repository has required methods.
     */
    public function test_has_required_methods()
    {
        $cache = m::mock(CacheRepository::class);
        $settings = m::mock(SettingsRepositoryInterface::class);
        $repository = new BumpQuotaRepository($cache, $settings);

        $this->assertTrue(method_exists($repository, 'getQuotaCounts'));
        $this->assertTrue(method_exists($repository, 'getLastManualBump'));
        $this->assertTrue(method_exists($repository, 'createBump'));
        $this->assertTrue(method_exists($repository, 'getRecentBumps'));
        $this->assertTrue(method_exists($repository, 'getTotalBumpCount'));
        $this->assertTrue(method_exists($repository, 'getDailyBumpCount'));
        $this->assertTrue(method_exists($repository, 'getWeeklyBumpCount'));
        $this->assertTrue(method_exists($repository, 'invalidateQuotaCache'));
        $this->assertTrue(method_exists($repository, 'invalidateStatsCache'));
        $this->assertTrue(method_exists($repository, 'getQuotaSettings'));
    }
}
