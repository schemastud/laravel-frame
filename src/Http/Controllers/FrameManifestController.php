<?php

namespace Schemastud\Frame\Http\Controllers;

use Schemastud\Frame\Registry\AdminResourceRegistry;
use Schemastud\Frame\Registry\ContextManifest;

/**
 * GET /frame/manifest -> { resources: AdminResourceDefinition[], contexts: {key => block} }.
 * Resolves the whole editor wiring for every registered resource; the frontend type
 * IS this projection (generate-once parity). Middleware/gating is the host's — the
 * route applies config('frame.middleware') so a host can put the surface behind its
 * own staff gate (numero: can:bypass-marquee).
 *
 * `contexts` is a top-level map keyed by resource key (byNode pointers are
 * resource-local). Additive: the `resources` shape is unchanged — the JS
 * WidgetContextRegistry reads `contexts[<key>]` for the `{byNode, inherits, known}`
 * block, or projects each property's embedded `x-stud-widget-contexts` directly.
 */
class FrameManifestController
{
    public function __invoke(AdminResourceRegistry $registry): array
    {
        $manifest = new ContextManifest;

        $contexts = [];

        foreach ($registry->all() as $definition) {
            $contexts[$definition->key] = $manifest->forResource($definition->data);
        }

        return [
            'resources' => $registry->all(),
            'contexts' => $contexts,
        ];
    }
}
