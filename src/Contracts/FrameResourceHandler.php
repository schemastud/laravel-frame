<?php

namespace Schemastud\Frame\Contracts;

use Schemastud\Frame\Http\Controllers\FrameResourceController;
use Schemastud\Frame\Registry\ResourceDefinition;

/**
 * The per-resource CRUD plug behind Frame's resource socket. The package ships the
 * uniform socket (the {@see FrameResourceController}
 * that drives `@schemastud/frame`'s ListShell / EditShell over `{prefix}/resources/*`);
 * a host supplies the non-uniform plug — one handler per resource archetype, resolved
 * through a {@see FrameResourceHandlerResolver}. The socket is persistence-agnostic:
 * whether a row is a plain Eloquent model or a beam `SchemaRecord` projection is the
 * handler's concern, never the controller's.
 */
interface FrameResourceHandler
{
    /**
     * A page of list rows (each a plain assoc array keyed by field name). Honors the
     * `filter[...]` / `sort` params facets sends. May return a flat array (the
     * controller paginates) or a pre-paginated `{data,total,page,perPage}` envelope.
     *
     * @return array<int, array<string, mixed>>
     */
    public function index(ResourceDefinition $definition, array $params): array;

    /**
     * One record projected through the edit shape (editData ?? data) to pre-fill the
     * form. MUST carry an `id`.
     *
     * @return array<string, mixed>
     */
    public function show(ResourceDefinition $definition, string $id): array;

    /**
     * Create from input; returns the freshly-projected row.
     *
     * @return array<string, mixed>
     */
    public function store(ResourceDefinition $definition, array $input): array;

    /**
     * Update from input; returns the freshly-projected row.
     *
     * @return array<string, mixed>
     */
    public function update(ResourceDefinition $definition, string $id, array $input): array;

    /** Remove a record. */
    public function destroy(ResourceDefinition $definition, string $id): void;
}
