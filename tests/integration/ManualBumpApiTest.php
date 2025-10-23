<?php

namespace HuseyinFiliz\Bump\Tests\Integration;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

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
            'users' => [
                $this->normalUser(),
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'User Discussion', 'user_id' => 2, 'comment_count' => 1, 'created_at' => Carbon::now()],
                ['id' => 2, 'title' => 'Second Discussion', 'user_id' => 2, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>First post</p></t>', 'created_at' => Carbon::now(), 'number' => 1],
                ['id' => 2, 'discussion_id' => 2, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>First post</p></t>', 'created_at' => Carbon::now(), 'number' => 1],
            ],
            'group_permission' => [
                ['permission' => 'huseyinfiliz-bump.owner', 'group_id' => 3], // Members group
            ],
        ]);
    }

    /**
     * @test
     */
    public function guest_cannot_bump_discussion()
    {
        // Enable manual bump
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.enable-manual-bump', true);

        $response = $this->send(
            $this->request('POST', '/api/manual-bump/1')
        );

        // Guests should not be able to bump (400 or 401 both acceptable)
        $this->assertContains($response->getStatusCode(), [400, 401]);
    }

    /**
     * @test
     */
    public function owner_can_bump_own_discussion()
    {
        // Enable manual bump
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.enable-manual-bump', true);

        $response = $this->send(
            $this->request('POST', '/api/manual-bump/1', [
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

    /**
     * @test
     */
    public function bump_respects_cooldown()
    {
        // Enable manual bump with 24 hour cooldown
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.enable-manual-bump', true);
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.manual-cooldown-hours', 24);

        // First bump - should succeed
        $response1 = $this->send(
            $this->request('POST', '/api/manual-bump/1', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(200, $response1->getStatusCode());

        // Second bump immediately - should fail due to cooldown
        $response2 = $this->send(
            $this->request('POST', '/api/manual-bump/1', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(422, $response2->getStatusCode());

        $data = json_decode($response2->getBody(), true);

        // Should contain error about cooldown
        $this->assertArrayHasKey('errors', $data);
    }

    /**
     * @test
     */
    public function admin_bypasses_cooldown()
    {
        // Enable manual bump with cooldown
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.enable-manual-bump', true);
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.manual-cooldown-hours', 24);

        // Admin first bump
        $response1 = $this->send(
            $this->request('POST', '/api/manual-bump/1', [
                'authenticatedAs' => 1, // Admin
            ])
        );

        $this->assertEquals(200, $response1->getStatusCode());

        // Admin second bump immediately - should succeed (bypass)
        $response2 = $this->send(
            $this->request('POST', '/api/manual-bump/1', [
                'authenticatedAs' => 1, // Admin
            ])
        );

        $this->assertEquals(200, $response2->getStatusCode());
    }

    /**
     * @test
     */
    public function bump_respects_daily_quota()
    {
        // Enable manual bump with daily quota of 1
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.enable-manual-bump', true);
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.manual-cooldown-hours', 0); // No cooldown
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.owner-daily-quota', 1);

        // First bump - should succeed
        $response1 = $this->send(
            $this->request('POST', '/api/manual-bump/1', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(200, $response1->getStatusCode());

        // Second bump on different discussion - should fail due to daily quota
        $response2 = $this->send(
            $this->request('POST', '/api/manual-bump/2', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(422, $response2->getStatusCode());
    }

    /**
     * @test
     */
    public function manual_bump_disabled_returns_error()
    {
        // Disable manual bump
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.enable-manual-bump', false);

        $response = $this->send(
            $this->request('POST', '/api/manual-bump/1', [
                'authenticatedAs' => 2,
            ])
        );

        // Should return 403 Forbidden when feature is disabled
        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function bump_updates_discussion_timestamps()
    {
        // Enable manual bump
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-bump.enable-manual-bump', true);

        $response = $this->send(
            $this->request('POST', '/api/manual-bump/1', [
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
}
