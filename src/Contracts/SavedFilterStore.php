<?php

namespace Schemastud\Frame\Contracts;

/**
 * OPTIONAL plug for persisting facets saved-views. When a host binds an implementation,
 * the socket's `{prefix}/saved-filters` endpoints delegate to it; when unbound, the
 * socket falls back to a transient stub (empty read, echo-back write, no-op delete) so
 * the facets SavedViews affordance still mounts and acts without a backing store — the
 * "generic fallback" the seam-is-a-registry doctrine calls for. Bind as:
 *
 *   $app->bind(SavedFilterStore::class, YourSavedFilters::class);
 */
interface SavedFilterStore
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(string $resource): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function save(string $resource, array $payload): array;

    public function delete(string $id): void;
}
