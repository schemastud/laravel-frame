<?php

namespace Schemastud\Frame\Summary;

use Illuminate\Contracts\Container\Container;
use LogicException;
use Schemastud\Frame\Authorization\ResourceAuthorizer;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Contracts\ResourceSummaryProvider;
use Schemastud\Frame\Data\SummaryResponseData;
use Schemastud\Frame\Filters\ResourceFilters;
use Schemastud\Frame\Registry\ResourceDefinition;

/**
 * Authorized resource summary behavior — the twin of {@see ResourceFilters} for the summary capability.
 *
 * The order is the same and it is load-bearing: the definition is found (404) and the access gate is
 * asked BEFORE the provider is ever resolved, so a refused resource never instantiates its provider.
 */
class ResourceSummaries
{
    public function __construct(
        private ResourceRegistry $registry,
        private ResourceAuthorizer $authorizer,
        private Container $container,
    ) {}

    /**
     * Find the resource and clear the access gate, or abort.
     *
     * PRIVATE, where the filter twin's is public: {@see ResourceFilters::definition()} is public because
     * callers outside frame genuinely reach for it (beam's own filters controller and saved-filter service
     * run the gate through it before answering on their own runtime). Nothing addresses a summary except
     * this class's own {@see summary()}, and a capability's gate is not an entry point on the strength of
     * the twin's shape alone.
     */
    private function definition(string $key): ResourceDefinition
    {
        $resource = $this->registry->find($key);
        abort_if($resource === null, 404, "Unknown frame resource '{$key}'.");
        $this->authorizer->authorizeAccess($resource);

        return $resource;
    }

    /** The resource's summary, or a 404 when it names no provider or its provider declines. */
    public function summary(string $key): SummaryResponseData
    {
        $resource = $this->definition($key);
        $summary = $this->resolve($resource)?->summary($resource);
        abort_if($summary === null, 404, "The '{$key}' frame resource has no summary.");

        return $summary;
    }

    private function resolve(ResourceDefinition $resource): ?ResourceSummaryProvider
    {
        if ($resource->summaryProvider === null) {
            return null;
        }

        $provider = $this->container->make($resource->summaryProvider);
        if (! $provider instanceof ResourceSummaryProvider) {
            throw new LogicException("Summary provider for '{$resource->key}' must implement ResourceSummaryProvider.");
        }

        return $provider;
    }
}
