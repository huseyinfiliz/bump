<?php

namespace HuseyinFiliz\Bump\Tests\Unit;

use Carbon\Carbon;
use Flarum\Testing\unit\TestCase;
use HuseyinFiliz\Bump\BumpQuota;

class BumpQuotaTest extends TestCase
{
    /**
     * Test that BumpQuota model has correct table name.
     */
    public function test_has_correct_table_name()
    {
        $quota = new BumpQuota();
        $this->assertEquals('user_bump_quota', $quota->getTable());
    }

    /**
     * Test that BumpQuota model has timestamps disabled.
     */
    public function test_timestamps_disabled()
    {
        $quota = new BumpQuota();
        $this->assertFalse($quota->timestamps);
    }

    /**
     * Test that bumped_at is cast to datetime.
     */
    public function test_bumped_at_cast_to_datetime()
    {
        $quota = new BumpQuota();
        $casts = $quota->getCasts();

        $this->assertArrayHasKey('bumped_at', $casts);
        $this->assertEquals('datetime', $casts['bumped_at']);
    }

    /**
     * Test fillable attributes.
     */
    public function test_fillable_attributes()
    {
        $quota = new BumpQuota();
        $fillable = $quota->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('discussion_id', $fillable);
        $this->assertContains('bumped_at', $fillable);
    }
}
