<?php
// migrations/2025_01_02_000000_add_bump_quota_tracking.php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        // Kullanıcı bump quota takibi
        $schema->create('user_bump_quota', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('discussion_id')->unsigned();
            $table->timestamp('bumped_at');
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('discussion_id')->references('id')->on('discussions')->onDelete('cascade');
            
            $table->index(['user_id', 'bumped_at']);
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('user_bump_quota');
    }
];