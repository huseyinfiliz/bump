<?php

namespace HuseyinFiliz\Bump\Listeners;

use Flarum\Post\Event\Posted;
use Flarum\Settings\SettingsRepositoryInterface;
use Carbon\Carbon;
use Psr\Log\LoggerInterface;

/**
 * Handles the bump absorber logic when a new post is created.
 *
 * This listener prevents discussions from being bumped to the top of the list
 * too frequently by checking if enough time has passed since the last bump.
 * If the threshold hasn't been met, it reverts the discussion's last_posted_at
 * values to prevent the bump.
 */
class HandlePostPosted
{
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var \HuseyinFiliz\Bump\Services\BumpSettingsResolver
     */
    protected $resolver;

    /**
     * @param SettingsRepositoryInterface $settings
     * @param LoggerInterface $logger
     * @param \HuseyinFiliz\Bump\Services\BumpSettingsResolver $resolver
     */
    public function __construct(
        SettingsRepositoryInterface $settings,
        LoggerInterface $logger,
        \HuseyinFiliz\Bump\Services\BumpSettingsResolver $resolver
    ) {
        $this->settings = $settings;
        $this->logger = $logger;
        $this->resolver = $resolver;
    }

    /**
     * Handle the Posted event to implement bump absorber logic.
     *
     * The bump absorber prevents NEW discussions from being bumped too frequently
     * by checking the discussion's age (time since creation) against a threshold.
     *
     * Flow:
     * 1. Check if absorber is enabled and user has bypass permissions
     * 2. Check if discussion tags are allowed (if tag filtering is enabled)
     * 3. Allow first bump unconditionally
     * 4. For subsequent posts, check if discussion age exceeds threshold
     * 5. If discussion is new (age < threshold), prevent bump but preserve manual bumps
     * 6. If discussion is old enough, update last_bumped_at to allow bump
     *
     * Key fix: Compares discussion age (created_at), not last bump time (last_bumped_at)
     * This ensures manual bumps are preserved when absorber is active.
     *
     * @param Posted $event The Posted event containing post and actor
     * @return void
     */
    public function handle(Posted $event): void
    {
        $post = $event->post;

        // Only handle comment posts
        if ($post->type !== 'comment') {
            return;
        }

        $discussion = $post->discussion;
        $actor = $event->actor;

        // CHECK 1: Is bump absorber enabled?
        $absorberEnabled = (bool) $this->settings->get('huseyinfiliz-bump.enable-absorber', false);

        if (!$absorberEnabled) {
            return;
        }

        // CHECK 2: Can user bypass absorber globally? (from absorber-bypass-groups)
        if ($this->resolver->canBypassAbsorberGlobally($actor)) {
            // User is in bypass groups - always allow bump
            return;
        }

        // CHECK 3: Get user's threshold (0 = bypass absorber via group override)
        $thresholdHours = $this->resolver->getThreshold($actor);

        if ($thresholdHours === 0) {
            // User can bypass absorber (group override with threshold: 0)
            return;
        }

        // CHECK 4: Tag control
        $allowedTags = json_decode($this->settings->get('huseyinfiliz-bump.absorber-tags', '[]'), true);

        if (!empty($allowedTags)) {
            $discussionTags = $discussion->tags()->pluck('id')->toArray();

            // Discussion must have at least one allowed tag
            if (empty(array_intersect($allowedTags, $discussionTags))) {
                return; // Absorber won't work on this discussion
            }
        }

        $lastBumpedAt = $discussion->last_bumped_at;

        // SPECIAL CASE: Never been bumped (first comment)
        if (!$lastBumpedAt) {
            // First bump - allow and set last_bumped_at
            $discussion->last_bumped_at = $post->created_at;
            $discussion->saveQuietly();

            $this->logger->debug('Bump: First bump allowed', [
                'discussion_id' => $discussion->id,
                'post_id' => $post->id,
            ]);

            return;
        }

        // Now last_bumped_at definitely exists
        $currentPostTime = $post->created_at;

        // CRITICAL FIX: Check discussion AGE, not time since last bump
        // This prevents manual bumps from being incorrectly reverted
        $hoursSinceCreation = $currentPostTime->diffInHours($discussion->created_at);

        // Is discussion still within threshold period?
        if ($hoursSinceCreation < $thresholdHours) {
            // BUMP BLOCKED - Discussion is too new

            // Find the previous post (right before this new one)
            $previousPost = $discussion->posts()
                ->where('type', 'comment')
                ->where('id', '<', $post->id)
                ->orderBy('id', 'desc')
                ->first();

            if ($previousPost) {
                // MANUAL BUMP PROTECTION:
                // If last_bumped_at is newer than the previous post,
                // it means a manual bump was performed. Preserve it!
                if ($lastBumpedAt && $lastBumpedAt > $previousPost->created_at) {
                    // Manual bump detected - keep the bumped position
                    $discussion->last_posted_at = $lastBumpedAt;
                    $discussion->last_posted_user_id = $previousPost->user_id;
                    $discussion->last_post_id = $previousPost->id;
                    $discussion->last_post_number = $previousPost->number;

                    $this->logger->debug('Bump: Blocked but manual bump preserved', [
                        'discussion_id' => $discussion->id,
                        'post_id' => $post->id,
                        'hours_since_creation' => $hoursSinceCreation,
                        'threshold' => $thresholdHours,
                        'manual_bump_at' => $lastBumpedAt,
                        'previous_post' => $previousPost->id,
                    ]);
                } else {
                    // Normal revert to previous post
                    $discussion->last_posted_at = $previousPost->created_at;
                    $discussion->last_posted_user_id = $previousPost->user_id;
                    $discussion->last_post_id = $previousPost->id;
                    $discussion->last_post_number = $previousPost->number;

                    $this->logger->debug('Bump: Blocked and reverted', [
                        'discussion_id' => $discussion->id,
                        'post_id' => $post->id,
                        'hours_since_creation' => $hoursSinceCreation,
                        'threshold' => $thresholdHours,
                        'reverted_to_post' => $previousPost->id,
                    ]);
                }

                // IMPORTANT: last_bumped_at does NOT change
                $discussion->saveQuietly();
            } else {
                // No previous post (shouldn't happen after first bump check)
                $discussion->last_posted_at = $discussion->created_at;
                $discussion->saveQuietly();

                $this->logger->debug('Bump: Blocked, no previous post', [
                    'discussion_id' => $discussion->id,
                    'post_id' => $post->id,
                ]);
            }

        } else {
            // THRESHOLD PASSED - BUMP ALLOWED
            // Discussion is old enough, allow normal bumping behavior

            // Update last_bumped_at (this bump is successful)
            $discussion->last_bumped_at = $currentPostTime;
            $discussion->saveQuietly();

            $this->logger->debug('Bump: Allowed (discussion old enough)', [
                'discussion_id' => $discussion->id,
                'post_id' => $post->id,
                'hours_since_creation' => $hoursSinceCreation,
                'threshold' => $thresholdHours,
            ]);
        }
    }
}
