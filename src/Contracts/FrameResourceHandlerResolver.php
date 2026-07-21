<?php

namespace Schemastud\Frame\Contracts;

/**
 * Resolves the {@see FrameResourceHandler} for a resource key — the selector at Frame's
 * resource seam. The host binds an implementation (its map of resource key → handler,
 * with a zero-code default fallback); the package's socket calls `handlerFor()` at
 * request time. Bind in the container as:
 *
 *   $app->bind(FrameResourceHandlerResolver::class, YourRegistry::class);
 */
interface FrameResourceHandlerResolver
{
    public function handlerFor(string $resource): FrameResourceHandler;
}
