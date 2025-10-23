<?php

namespace HuseyinFiliz\Bump;

use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\User\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property int $user_id
 * @property int $discussion_id
 * @property Carbon $bumped_at
 * @property User $user
 * @property Discussion $discussion
 */
class BumpQuota extends AbstractModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_bump_quota';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'bumped_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['user_id', 'discussion_id', 'bumped_at'];

    /**
     * Get the user that performed the bump.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the discussion that was bumped.
     */
    public function discussion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }

    /**
     * Scope a query to only include bumps by a specific user.
     *
     * @param Builder $query
     * @param int $userId The user ID to filter by
     * @return Builder
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include bumps for a specific discussion.
     *
     * @param Builder $query
     * @param int $discussionId The discussion ID to filter by
     * @return Builder
     */
    public function scopeForDiscussion(Builder $query, int $discussionId): Builder
    {
        return $query->where('discussion_id', $discussionId);
    }

    /**
     * Scope a query to only include bumps since a specific date.
     *
     * @param Builder $query
     * @param Carbon $date The date to filter from (inclusive)
     * @return Builder
     */
    public function scopeSince(Builder $query, Carbon $date): Builder
    {
        return $query->where('bumped_at', '>=', $date);
    }

    /**
     * Scope a query to only include recent bumps.
     *
     * Orders by bumped_at descending and limits results.
     *
     * @param Builder $query
     * @param int $limit Maximum number of results (default: 10)
     * @return Builder
     */
    public function scopeRecent(Builder $query, int $limit = 10): Builder
    {
        return $query->orderBy('bumped_at', 'desc')->limit($limit);
    }

    /**
     * Get quota counts for a user within time periods.
     *
     * Returns daily and weekly bump counts for the specified user.
     * Uses a single optimized query with CASE statements to avoid N+1 queries.
     *
     * @param int $userId The user ID to get quota counts for
     * @return object Object with daily_count and weekly_count properties
     */
    public static function getQuotaCounts(int $userId): object
    {
        $now = Carbon::now();

        $result = static::where('user_id', $userId)
            ->selectRaw('
                COUNT(CASE WHEN bumped_at >= ? THEN 1 END) as daily_count,
                COUNT(CASE WHEN bumped_at >= ? THEN 1 END) as weekly_count
            ', [$now->copy()->subDay(), $now->copy()->subWeek()])
            ->first();

        return (object) [
            'daily_count' => $result ? (int) $result->daily_count : 0,
            'weekly_count' => $result ? (int) $result->weekly_count : 0,
        ];
    }

    /**
     * Get the last manual bump for a user and discussion.
     *
     * Used for cooldown calculations. Returns the most recent bump record
     * for the specified user and discussion combination.
     *
     * @param int $userId The user ID
     * @param int $discussionId The discussion ID
     * @return self|null The last bump record or null if none found
     */
    public static function getLastManualBump(int $userId, int $discussionId): ?self
    {
        return static::forUser($userId)
            ->forDiscussion($discussionId)
            ->orderBy('bumped_at', 'desc')
            ->first();
    }
}
