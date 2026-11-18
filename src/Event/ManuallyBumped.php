<?php

namespace HuseyinFiliz\Bump\Event;

use Flarum\Discussion\Discussion;
use Flarum\User\User;

class ManuallyBumped
{
    public function __construct(
        public Discussion $discussion,
        public User $actor,
        public bool $moderatorBypass
    ) {
    }
}
