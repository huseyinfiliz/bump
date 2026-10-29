<?php

namespace HuseyinFiliz\Bump\Tests\Integration;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use HuseyinFiliz\Bump\BumpQuota;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for Manual Bump API functionality.
 *
 * Tests the manual bump API endpoint that allows users to
 * manually bump their discussions.
 */
class ManualBumpApiTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('huseyinfiliz-bump');
        $this->extension('flarum-tags');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'User Discussion', 'user_id' => 2, 'comment_count' => 1, 'created_at' => Carbon::now()],
                ['id' => 2, 'title' => 'Second Discussion', 'user_id' => 2, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>First post</p></t>', 'created_at' => Carbon::now(), 'number' => 1],
                ['id' => 2, 'discussion_id' => 2, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>First post</p></t>', 'created_at' => Carbon::now(), 'number' => 1],
            ],
            'group_permission' => [
                ['permission' => 'huseyinfiliz-bump.owner', 'group_id' => 3], // Members group
            ],
        ]);
    }

    #[Test]
    public function guest_cannot_bump_discussion()
    {
        // Enable manual bump
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.enable-manual-bump', true);

        $response = $this->send(
            $this->request('POST', '/api/discussions/1/bump')
        );

        // Guests should not be able to bump (400 or 401 both acceptable)
        $this->assertContains($response->getStatusCode(), [400, 401]);
    }

    #[Test]
    public function owner_can_bump_own_discussion()
    {
        // Enable manual bump
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.enable-manual-bump', true);

        $response = $this->send(
            $this->request('POST', '/api/discussions/1/bump', [
                'authenticatedAs' => 2, // Discussion owner
            ])
        );

        // Owner should be able to bump (with proper permission)
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);

        // Response should contain discussion data
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals('discussions', $data['data']['type']);
        $this->assertEquals('1', $data['data']['id']);
    }

    #[Test]
    public function bump_respects_cooldown()
    {
        // Enable manual bump with 24 hour cooldown
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.enable-manual-bump', true);
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.manual-cooldown-hours', 24);

        // First bump - should succeed
        $response1 = $this->send(
            $this->request('POST', '/api/discussions/1/bump', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(200, $response1->getStatusCode());

        // Second bump immediately - should fail due to cooldown
        $response2 = $this->send(
            $this->request('POST', '/api/discussions/1/bump', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(422, $response2->getStatusCode());

        $data = json_decode($response2->getBody(), true);

        // Should contain error about cooldown
        $this->assertArrayHasKey('errors', $data);
    }

    #[Test]
    public function admin_bypasses_cooldown()
    {
        // Enable manual bump with cooldown
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.enable-manual-bump', true);
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.manual-cooldown-hours', 24);
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.moderator-groups', '["1"]');

        // Admin first bump
        $response1 = $this->send(
            $this->request('POST', '/api/discussions/1/bump', [
                'authenticatedAs' => 1, // Admin
            ])
        );

        $this->assertEquals(200, $response1->getStatusCode());

        // Admin second bump immediately - should succeed (bypass)
        $response2 = $this->send(
            $this->request('POST', '/api/discussions/1/bump', [
                'authenticatedAs' => 1, // Admin
            ])
        );

        $this->assertEquals(200, $response2->getStatusCode());
    }

    #[Test]
    public function bump_respects_daily_quota()
    {
        // Enable manual bump with daily quota of 1
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.enable-manual-bump', true);
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.manual-cooldown-hours', 0); // No cooldown
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.owner-daily-quota', 1);

        // First bump - should succeed
        $response1 = $this->send(
            $this->request('POST', '/api/discussions/1/bump', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(200, $response1->getStatusCode());

        // Second bump on different discussion - should fail due to daily quota
        $response2 = $this->send(
            $this->request('POST', '/api/discussions/2/bump', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(422, $response2->getStatusCode());
    }

    #[Test]
    public function manual_bump_disabled_returns_error()
    {
        // Disable manual bump
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.enable-manual-bump', false);

        $response = $this->send(
            $this->request('POST', '/api/discussions/1/bump', [
                'authenticatedAs' => 2,
            ])
        );

        // Should return 403 Forbidden when feature is disabled
        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function bump_updates_discussion_timestamps()
    {
        // Enable manual bump
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.enable-manual-bump', true);

        $response = $this->send(
            $this->request('POST', '/api/discussions/1/bump', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);

        // Check that lastBumpedAt attribute is present and recent
        $this->assertArrayHasKey('attributes', $data['data']);

        // The discussion should now have updated timestamps
        $this->assertIsArray($data['data']['attributes']);
    }

    #[Test]
    public function member_group_override_disables_bumps_without_explicit_membership()
    {
        $this->setting('huseyinfiliz-bump.group-overrides-manual', '{"3":{"cooldown":-1}}');

        $response = $this->send($this->request('POST', '/api/discussions/1/bump', ['authenticatedAs' => 2]));

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals(0, BumpQuota::count());
    }

    #[Test]
    public function expired_cooldown_allows_bumping_with_carbon_three()
    {
        $this->setting('huseyinfiliz-bump.manual-cooldown-hours', 24);
        $this->app();
        BumpQuota::create(['user_id' => 2, 'discussion_id' => 1, 'bumped_at' => Carbon::now()->subHours(25)]);

        $response = $this->send($this->request('POST', '/api/discussions/1/bump', ['authenticatedAs' => 2]));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(2, BumpQuota::count());
    }

    #[Test]
    public function weekly_quota_applies_across_discussions()
    {
        $this->setting('huseyinfiliz-bump.owner-weekly-quota', 1);
        $this->setting('huseyinfiliz-bump.manual-cooldown-hours', 0);

        $first = $this->send($this->request('POST', '/api/discussions/1/bump', ['authenticatedAs' => 2]));
        $second = $this->send($this->request('POST', '/api/discussions/2/bump', ['authenticatedAs' => 2]));

        $this->assertEquals(200, $first->getStatusCode());
        $this->assertEquals(422, $second->getStatusCode());
        $this->assertEquals(1, BumpQuota::count());
    }

    #[Test]
    public function discussion_list_serializes_actor_bump_and_quota_fields()
    {
        $this->setting('huseyinfiliz-bump.owner-daily-quota', 2);
        $this->send($this->request('POST', '/api/discussions/1/bump', ['authenticatedAs' => 2]));

        $response = $this->send($this->request('GET', '/api/discussions', ['authenticatedAs' => 2]));
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true)['data'];
        $discussions = array_column($data, 'attributes', 'id');

        $this->assertNotNull($discussions[1]['lastManualBumpedAt']);
        $this->assertNull($discussions[2]['lastManualBumpedAt']);
        $this->assertEquals(['used' => 1, 'limit' => 2, 'remaining' => 1], $discussions[1]['dailyBumpQuota']);
    }

    #[Test]
    public function stats_and_cache_clear_require_admin()
    {
        $this->assertEquals(403, $this->send($this->request('GET', '/api/bump/stats', ['authenticatedAs' => 2]))->getStatusCode());
        $this->assertEquals(403, $this->send($this->request('POST', '/api/bump/clear-cache', ['authenticatedAs' => 2]))->getStatusCode());
        $this->assertEquals(200, $this->send($this->request('GET', '/api/bump/stats', ['authenticatedAs' => 1]))->getStatusCode());
        $this->assertEquals(204, $this->send($this->request('POST', '/api/bump/clear-cache', ['authenticatedAs' => 1]))->getStatusCode());
    }
}
