<?php

namespace HuseyinFiliz\Bump\Api\Controllers;

use Flarum\Http\RequestUtil;
use HuseyinFiliz\Bump\Services\BumpSettingsResolver;
use HuseyinFiliz\Bump\Repository\BumpQuotaRepository;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Clear bump settings cache.
 *
 * This controller handles cache clearing when settings are saved from admin panel.
 * Clears only bump-related caches: resolver and quota stats.
 */
class ClearCacheController implements RequestHandlerInterface
{
    public function __construct(protected BumpSettingsResolver $resolver, protected BumpQuotaRepository $repository)
    {
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

        return new EmptyResponse(204);
    }
}