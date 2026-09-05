<?php

namespace Schemastud\Frame\Registry;

use Illuminate\Http\Request;
use Schemastud\Frame\Contracts\FrameNavContributor;

/**
 * Builds the `nav` + `routeContext` half of `/frame/manifest` by asking the optional
 * {@see FrameNavContributor} plug — the router/navigation sibling of {@see ContextManifest}, and
 * built to the same three rules that class documents:
 *
 *  - the port arrives by **nullable constructor argument**, never by service location, so
 *    `new NavManifest` works and the class is testable with no container;
 *  - an unbound host gets `[]`, which spreads into the payload as nothing at all — the two keys
 *    that were always there stay the only two keys;
 *  - the block is computed **on read**, per request, so a realm that gains a navigation later
 *    is picked up rather than load order being recorded as truth.
 *
 * The `nav` value is an already-resolved array (the contributor's own tree, gated and
 * active-stamped by whatever navigation engine the tier above runs); frame does not model
 * navigation and deliberately learns nothing about how the tree was built.
 */
class NavManifest
{
    public function __construct(
        protected ?FrameNavContributor $contributor = null,
    ) {}

    /**
     * The realm a manifest request is scoped to: a route default, because a host mounts the same
     * controller once per realm and the realm is a fact about the MOUNT, not about the caller.
     * Null when the host mounts it once, unscoped — which is frame's own default route.
     */
    public static function realmFor(Request $request): ?string
    {
        $realm = $request->route()?->defaults['realm'] ?? null;

        return is_string($realm) && $realm !== '' ? $realm : null;
    }

    /**
     * @return array{}|array{nav: array<string, mixed>, routeContext: array<int, RouteContextEntry>}
     */
    public function forRealm(?string $realm = null): array
    {
        $block = $this->contributor?->contributeNav($realm);

        if ($block === null) {
            return [];
        }

        return [
            'nav' => $block['nav'],
            'routeContext' => $block['routeContext'],
        ];
    }
}
