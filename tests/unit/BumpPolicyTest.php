<?php

namespace HuseyinFiliz\Bump\Tests\Unit;

use Flarum\Testing\unit\TestCase;
use HuseyinFiliz\Bump\Access\BumpPolicy;
use HuseyinFiliz\Bump\Services\BumpSettingsResolver;
use Flarum\Settings\SettingsRepositoryInterface;
use Mockery as m;

class BumpPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        m::close();
    }

    /**
     * Test that BumpPolicy can be instantiated.
     */
    public function test_can_instantiate_policy()
    {
        $settings = m::mock(SettingsRepositoryInterface::class);
        $resolver = m::mock(BumpSettingsResolver::class);
        $policy = new BumpPolicy($settings, $resolver);

        $this->assertInstanceOf(BumpPolicy::class, $policy);
    }

    /**
     * Test that BumpPolicy has bump method.
     */
    public function test_has_bump_method()
    {
        $settings = m::mock(SettingsRepositoryInterface::class);
        $resolver = m::mock(BumpSettingsResolver::class);
        $policy = new BumpPolicy($settings, $resolver);

        $this->assertTrue(method_exists($policy, 'bump'));
    }
}
