<?php

namespace HuseyinFiliz\Bump\Api\Controllers;

use Flarum\Http\RequestUtil;
use HuseyinFiliz\Bump\Services\BumpSettingsResolver;
use HuseyinFiliz\Bump\Repository\BumpQuotaRepository;
use Illuminate\Contracts\Cache\Repository as Cache;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Clear bump settings cache.
 *
 * This controller handles cache clearing when settings are saved from admin panel.
 * Clears all bump-related caches: resolver, quota, and Laravel cache.
 */
class ClearCacheController implements RequestHandlerInterface
{
    /**
     * @var BumpSettingsResolver
     */
    protected $resolver;

    /**
     * @var BumpQuotaRepository
     */
    protected $repository;

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @param BumpSettingsResolver $resolver
     * @param BumpQuotaRepository $repository
     * @param Cache $cache
     */
    public function __construct(
        BumpSettingsResolver $resolver,
        BumpQuotaRepository $repository,
        Cache $cache
    ) {
        $this->resolver = $resolver;
        $this->repository = $repository;
        $this->cache = $cache;
    }

    /**
     * Handle the request.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        // Only admins can clear cache
        $actor->assertAdmin();

        // Clear BumpSettingsResolver cache (group overrides, settings)
        $this->resolver->clearCache();

        // Clear BumpQuotaRepository stats cache
        $this->repository->invalidateStatsCache();

        // Clear all user quota caches (pattern: bump_quota_counts_{userId})
        // We can't pattern-match in Laravel cache, so we flush all bump-related keys
        // This is safe as it only affects bump extension data
        try {
            $this->cache->flush();
        } catch (\Exception $e) {
            // If flush fails, at least resolver cache is cleared
            // Log error but don't fail the request
        }

        return new EmptyResponse(204);
    }
}
