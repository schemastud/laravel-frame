<?php

namespace Schemastud\Frame\Contracts;

/**
 * The host's `x-filter` / `x-sort` schema per resource — what `@schemastud/facets`
 * consumes to render the facets bar — plus the option source for `select` facets
 * (facets' MultiSelect reads options only via `optionsRef`, never inline). The package
 * socket exposes these at `{prefix}/filter-schema/{resource}` and
 * `{prefix}/filter-options/{ref}`; the host binds the provider:
 *
 *   $app->bind(FrameFilterProvider::class, YourFilterSchemas::class);
 */
interface FrameFilterProvider
{
    /**
     * @return array{properties: array<string, array<string, mixed>>}
     */
    public function for(string $resource): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function options(string $ref): array;
}
