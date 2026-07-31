<?php

namespace Schemastud\Frame\Contracts;

use Schemastud\Frame\Http\Controllers\FrameManifestController;
use Schemastud\Frame\Http\Controllers\FrameResourceController;
use Schemastud\Frame\Registry\ResourceDefinition;

/**
 * Frame's read-only registry PORT — the manifest source frame's own machinery reads.
 * Frame's {@see FrameManifestController} and
 * {@see FrameResourceController} resolve resource
 * wiring through this contract; they never name a concrete registry.
 *
 * The PRODUCER of the definitions — the thing that owns the resource *declaration*
 * (the attribute / discovery / registration) — lives ABOVE frame in the dependency
 * graph (the consuming CMS engine's resource registry). Frame defines the port; the
 * producer binds its implementation. This inverts the coupling so frame never
 * imports a producer type: frame is handed definitions, it does not know how they
 * were declared. A host without a producer binds no implementation (the manifest
 * routes simply resolve an empty registry, or are switched off via config).
 */
interface ResourceRegistry
{
    public function has(string $key): bool;

    public function get(string $key): ResourceDefinition;

    /**
     * The served manifest: flat sibling entries, insertion order.
     *
     * @return list<ResourceDefinition>
     */
    public function all(): array;
}
