<?php

namespace Schemastud\Frame\Http\Controllers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use LogicException;
use Rushing\LaravelDataSchemasScribe\Attributes\QueryFromData;
use Rushing\LaravelDataSchemasScribe\Attributes\ResponseFromData;
use Schemastud\Frame\Authorization\ResourceAuthorizer;
use Schemastud\Frame\Contracts\ResourceFilterProvider;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Data\FilterOptionsQueryData;
use Schemastud\Frame\Data\FilterOptionsResponseData;
use Schemastud\Frame\Data\FilterSchemaResponseData;
use Schemastud\Frame\Data\FilterVariantsData;
use Schemastud\Frame\Data\FilterVariantsResponseData;
use Schemastud\Frame\Registry\ResourceDefinition;

/** Filter capabilities of an addressed Frame resource. */
class FrameResourceFiltersController
{
    public function __construct(
        private ResourceRegistry $registry,
        private ResourceAuthorizer $authorizer,
        private Container $container,
    ) {}

    /** Get resource filter schema */
    #[ResponseFromData(FilterSchemaResponseData::class)]
    public function schema(Request $request): array
    {
        $resource = $this->definition($request);
        $provider = $this->provider($resource);
        $variant = $request->route('variant');
        abort_if($provider === null && $variant !== null, 404);

        return ($provider?->schema($resource, $variant)
            ?? new FilterSchemaResponseData(['type' => 'object', 'properties' => (object) []]))->toArray();
    }

    /** Get resource filter options */
    #[QueryFromData(FilterOptionsQueryData::class)]
    #[ResponseFromData(FilterOptionsResponseData::class)]
    public function options(Request $request): array
    {
        $resource = $this->definition($request);
        $provider = $this->provider($resource);
        abort_if($provider === null, 404);
        $query = FilterOptionsQueryData::validateAndCreate($request->query());

        return $provider->options(
            $resource,
            (string) $request->route('ref'),
            is_string($query->search) ? $query->search : null,
        )->toArray();
    }

    /** List resource filter variants */
    #[ResponseFromData(FilterVariantsResponseData::class)]
    public function variants(Request $request): array
    {
        $resource = $this->definition($request);

        return ($this->provider($resource)?->variants($resource)
            ?? new FilterVariantsResponseData(new FilterVariantsData($resource->key, [])))->toArray();
    }

    private function definition(Request $request): ResourceDefinition
    {
        $key = (string) $request->route('resource');
        $resource = $this->registry->find($key);
        abort_if($resource === null, 404, "Unknown frame resource '{$key}'.");
        $this->authorizer->authorizeAccess($resource);

        return $resource;
    }

    private function provider(ResourceDefinition $resource): ?ResourceFilterProvider
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
