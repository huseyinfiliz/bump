<?php

namespace HuseyinFiliz\Bump\Tests\Integration;

use Carbon\Carbon;
use Flarum\Audit\AuditLog;
use Flarum\Audit\AuditLogger;
use Flarum\Discussion\Discussion;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use HuseyinFiliz\Bump\BumpQuota;
use HuseyinFiliz\Bump\Event\ManuallyBumped;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;

class AuditTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        AuditLogger::$testMode = true;
        $this->extension('flarum-audit');
        $this->extension('huseyinfiliz-bump');
        $this->extension('flarum-tags');
        $this->setting('huseyinfiliz-bump.manual-cooldown-hours', 24);
        $this->setting('huseyinfiliz-bump.moderator-groups', '["1"]');
        $this->prepareDatabase([
            'audit_log' => [],
            User::class => [$this->normalUser()],
            Discussion::class => [
                ['id' => 1, 'title' => 'Audit test discussion', 'user_id' => 2, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
        ]);
    }

    #[Test]
    public function owner_bump_is_logged_once_and_rejection_is_not_logged()
    {
        $first = $this->send($this->request('POST', '/api/discussions/1/bump', ['authenticatedAs' => 2]));
        $second = $this->send($this->request('POST', '/api/discussions/1/bump', ['authenticatedAs' => 2]));

        $this->assertEquals(200, $first->getStatusCode());
        $this->assertEquals(422, $second->getStatusCode());
        $logs = AuditLog::where('action', 'discussion.manually_bumped')->get();
        $this->assertCount(1, $logs);
        $this->assertEquals(2, $logs->first()->actor_id);
        $this->assertEquals(['discussion_id' => 1, 'moderator_bypass' => false], $logs->first()->payload);
        $this->assertContains('discussion.manually_bumped', AuditLogger::$registeredActions['huseyinfiliz-bump']);
    }

    #[Test]
    public function moderator_bump_is_logged_without_consuming_owner_quota()
    {
        $response = $this->send($this->request('POST', '/api/discussions/1/bump', ['authenticatedAs' => 1]));

        $this->assertEquals(200, $response->getStatusCode());
        $log = AuditLog::where('action', 'discussion.manually_bumped')->first();
        $this->assertNotNull($log);
        $this->assertEquals(1, $log->actor_id);
        $this->assertEquals(['discussion_id' => 1, 'moderator_bypass' => true], $log->payload);
        $this->assertEquals(0, BumpQuota::count());
    }

    #[Test]
    public function listener_failure_rolls_back_the_bump_and_its_audit_entry()
    {
        $container = $this->app()->getContainer();
        $before = Discussion::find(1)->getRawOriginal('last_posted_at');
        $container->make(Dispatcher::class)->listen(ManuallyBumped::class, function () {
            throw new \RuntimeException('Simulated listener failure after audit logging');
        });

        $response = $this->send($this->request('POST', '/api/discussions/1/bump', ['authenticatedAs' => 2]));

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals($before, Discussion::find(1)->getRawOriginal('last_posted_at'));
        $this->assertNull(Discussion::find(1)->last_bumped_at);
        $this->assertEquals(0, BumpQuota::count());
        $this->assertEquals(0, AuditLog::where('action', 'discussion.manually_bumped')->count());
    }
}
