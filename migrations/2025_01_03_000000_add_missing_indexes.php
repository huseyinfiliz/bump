<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('user_bump_quota', function (Blueprint $table) {
            // Add index for cooldown queries (per discussion)
            $table->index(['discussion_id', 'bumped_at']);
        });
    },
    'down' => function (Builder $schema) {
        // Don't drop the index here - it will fail due to foreign key constraint
        // The table will be completely dropped in the next rollback (2025_01_02)
        // which automatically removes all indexes and foreign keys
    }
];
