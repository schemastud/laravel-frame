<?php

namespace Schemastud\Frame\Http\Controllers;

use Illuminate\Http\Request;
use Schemastud\Frame\Authorization\ResourceAuthorizer;
use Schemastud\Frame\Contracts\FrameNavContributor;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Registry\ContextManifest;
use Schemastud\Frame\Registry\NavManifest;

/**
 * GET /frame/manifest -> { resources: ResourceDefinition[], contexts: {key => block} }
 * (+ { nav, routeContext } where a {@see FrameNavContributor} is bound).
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
 *
 * {@see NavManifest} is the same idiom one key over, and it closes a measured gap: `nav` and
 * `routeContext` were emitted by exactly ONE host in the estate, which got them by hand-writing a
 * private copy of this controller. The projection is now reachable from a package; the realm/nav
 * LIST it projects stays host-owned (api-surface-coherence 141/142). Bind nothing and the payload
 * is the same two keys it has always been — asserted, not assumed, in `NavManifestTest`.
 */
class FrameManifestController
{
    public function __invoke(
        Request $request,
        ResourceRegistry $registry,
        ContextManifest $manifest,
        NavManifest $nav,
        ResourceAuthorizer $authorizer,
    ): array {
        $contexts = [];

        foreach ($registry->all() as $definition) {
            $contexts[$definition->key] = $manifest->forResource(
                $definition->data,
                $definition->layout,
                $definition->key,
                $definition->resolvedCreateAffordance(),
                $definition->resolvedSingularLabel(),
                // The ACTOR axis. This makes the manifest response VARY BY USER, which it did not
                // before — a host caching it must key that cache on the viewer, or it will serve one
                // actor's affordances to another. The resources/contexts payload is otherwise
                // unchanged, and an anonymous request resolves every capability false.
                $authorizer->capabilities($definition),
            );
        }

        return [
            'resources' => $registry->all(),
            'contexts' => $contexts,
            // Spreads to NOTHING when no contributor is bound, which is every pure-frame host.
            ...$nav->forRealm(NavManifest::realmFor($request)),
        ];
    }
}
