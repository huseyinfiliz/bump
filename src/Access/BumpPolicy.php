<?php

namespace HuseyinFiliz\Bump\Access;

use Flarum\Discussion\Discussion;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;
use Flarum\Settings\SettingsRepositoryInterface;
use HuseyinFiliz\Bump\Services\BumpSettingsResolver;

class BumpPolicy extends AbstractPolicy
{
    protected $settings;
    protected $resolver;

    public function __construct(
        SettingsRepositoryInterface $settings,
        BumpSettingsResolver $resolver
    ) {
        $this->settings = $settings;
        $this->resolver = $resolver;
    }

    public function bump(User $actor, Discussion $discussion)
    {
        // CHECK 1: Manual bump enabled globally?
        if (!$this->settings->get('huseyinfiliz-bump.enable-manual-bump', false)) {
            return $this->deny();
        }

        // CHECK 2: Tag control (applies to everyone)
        $allowedTags = json_decode($this->settings->get('huseyinfiliz-bump.manual-bump-tags', '[]'), true);

        if (!empty($allowedTags)) {
            $discussionTags = $discussion->tags()->pluck('id')->toArray();

            // Discussion must have at least one allowed tag
            if (empty(array_intersect($allowedTags, $discussionTags))) {
                return $this->deny();
            }
        }

        // CHECK 3: Can user moderate bumps? (can bump anyone's discussion)
        if ($this->resolver->canModerateBumps($actor)) {
            return $this->allow();
        }

        // CHECK 4: Is it their own discussion AND is bump not disabled?
        if ($discussion->user_id === $actor->id) {
            // Allow if bump is not disabled for their group
            if (!$this->resolver->isBumpDisabled($actor)) {
                return $this->allow();
            }
        }

        return $this->deny();
    }
}