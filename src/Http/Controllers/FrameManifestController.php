<?php

namespace Schemastud\Frame\Http\Controllers;

use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Registry\ContextManifest;

/**
 * GET /frame/manifest -> { resources: ResourceDefinition[], contexts: {key => block} }.
 * Resolves the whole editor wiring for every registered resource; the frontend type
 * IS this projection (generate-once parity). Middleware/gating is the host's — the
 * route applies config('frame.middleware') so a host can put the surface behind its
 * own staff gate (numero: can:bypass-marquee).
 *
 * `contexts` is a top-level map keyed by resource key (byNode pointers are
 * resource-local). Additive: the `resources` shape is unchanged — the JS
 * WidgetContextRegistry reads `contexts[<key>]` for the `{byNode, inherits, known}`
 * block, or projects each property's embedded `x-stud-widget-contexts` directly.
 *
 * {@see ContextManifest} is RESOLVED rather than `new`'d so its optional
 * {@see \Schemastud\Frame\Contracts\ResourceContextContributor} plug is picked up where a
 * consumer binds one. In a pure-frame host nothing binds it, the nullable constructor
 * argument resolves to null, and every block here is byte-identical to before.
 */
class FrameManifestController
{
    public function __invoke(ResourceRegistry $registry, ContextManifest $manifest): array
    {
        $contexts = [];

        foreach ($registry->all() as $definition) {
            $contexts[$definition->key] = $manifest->forResource(
                $definition->data,
                $definition->layout,
                $definition->key,
            );
        }

        return [
            'resources' => $registry->all(),
            'contexts' => $contexts,
        ];
    }
}
