<?php

namespace HuseyinFiliz\Bump;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BumpQuotaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'discussion_id' => Discussion::factory(),
            'bumped_at' => Carbon::now(),
        ];
    }
}
