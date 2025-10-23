<?php

use Flarum\Database\Migration;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Database\Schema\Builder;

/**
 * Add default admin group overrides for bump settings.
 *
 * This migration adds default group overrides for the Admin group (group_id = 1)
 * with unlimited/bypass settings. This serves as an example for users and
 * automatically gives admins bypass privileges.
 */
return Migration::addSettings([
    // Manual bump overrides - Admin group gets unlimited bumps
    'huseyinfiliz-bump.group-overrides-manual' => json_encode([
        '1' => [ // Admin group ID
            'cooldown' => 0,  // No cooldown (unlimited)
            'daily' => 0,     // No daily limit (unlimited)
            'weekly' => 0,    // No weekly limit (unlimited)
        ],
    ]),

    // Absorber overrides - Admin group bypasses absorber
    'huseyinfiliz-bump.group-overrides-absorber' => json_encode([
        '1' => [ // Admin group ID
            'threshold' => 0, // Bypass absorber
        ],
    ]),

    // Moderator groups - Admin group can moderate bumps (bypass restrictions and bump any discussion)
    'huseyinfiliz-bump.moderator-groups' => json_encode(['1']), // Admin group ID
]);
