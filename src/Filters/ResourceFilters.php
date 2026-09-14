<?php

namespace Schemastud\Frame\Filters;

use Illuminate\Contracts\Container\Container;
use LogicException;
use Schemastud\Frame\Authorization\ResourceAuthorizer;
use Schemastud\Frame\Contracts\ResourceAccessGate;
use Schemastud\Frame\Contracts\ResourceFilterProvider;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Data\FilterOptionsResponseData;
use Schemastud\Frame\Data\FilterSchemaResponseData;
use Schemastud\Frame\Data\FilterVariantsData;
use Schemastud\Frame\Data\FilterVariantsResponseData;
use Schemastud\Frame\Data\ResourceCapabilitiesData;
use Schemastud\Frame\Registry\ResourceDefinition;

/** Authorized resource behavior, shared by every HTTP projection of Frame filters. */
class ResourceFilters
{
    public function __construct(
        private ResourceRegistry $registry,
        private ResourceAuthorizer $authorizer,
        private ResourceAccessGate $access,
        private Container $container,
    ) {}

    public function definition(string $key): ResourceDefinition
    {
        $resource = $this->registry->find($key);
        abort_if($resource === null, 404, "Unknown frame resource '{$key}'.");
        $this->authorizer->authorizeAccess($resource);

        return $resource;
    }

    public function provider(string $key): ?ResourceFilterProvider
    {
        return $this->resolve($this->definition($key));
    }

    public function schema(string $key, ?string $variant = null): FilterSchemaResponseData
    {
        $resource = $this->definition($key);
        $provider = $this->resolve($resource);
        abort_if($provider === null && $variant !== null, 404);
        $schema = $provider?->schema($resource, $variant)
            ?? new FilterSchemaResponseData(['type' => 'object', 'properties' => (object) []]);

        // A reference is a dependency on another resource, not permission to address it.
        // Resolve only its definition: a self-reference must never recurse into its provider.
        $saved = $schema->savedViewsResource === null ? null : $this->registry->find($schema->savedViewsResource);
        if ($saved === null || ! $this->access->allowsResource($saved)) {
            return new FilterSchemaResponseData($schema->data);
        }

        return new FilterSchemaResponseData(
            $schema->data,
            $saved->key,
            new ResourceCapabilitiesData(...$this->authorizer->capabilities($saved)),
        );
    }

    public function options(string $key, string $ref, ?string $search = null): FilterOptionsResponseData
    {
        $resource = $this->definition($key);
        $provider = $this->resolve($resource);
        abort_if($provider === null, 404);

        return $provider->options($resource, $ref, $search);
    }

    public function variants(string $key): FilterVariantsResponseData
    {
        $resource = $this->definition($key);

        return $this->resolve($resource)?->variants($resource)
            ?? new FilterVariantsResponseData(new FilterVariantsData($resource->key, []));
    }

    private function resolve(ResourceDefinition $resource): ?ResourceFilterProvider
    {
        if ($resource->filterProvider === null) {
            return null;
        }

        $provider = $this->container->make($resource->filterProvider);
        if (! $provider instanceof ResourceFilterProvider) {
            throw new LogicException("Filter provider for '{$resource->key}' must implement ResourceFilterProvider.");
        }

        return $provider;
    }
}
