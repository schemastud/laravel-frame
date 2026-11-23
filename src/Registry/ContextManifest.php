<?php

namespace Schemastud\Frame\Registry;

use ReflectionClass;
use Schemastud\Frame\Contracts\ResourceContextContributor;

/**
 * Builds the `{byNode, inherits, known}` render-context block for one resource's
 * Data class — the WidgetContextRegistry server-side projection the JS host consumes.
 * Top-level-resource-scoped: only the root and its DIRECT properties carry the
 * keyword; nested sub-DTOs are NOT recursed.
 *
 *  - `known`    every registered render context, in registration order ({@see WidgetContextRegistry}: frame's
 *               seven, plus any a package or host registers).
 *  - `inherits` each context's fallback edges, in registration order (frame's two: `row-cell` falls back to
 *               `edit`; `overview` falls back to `summary`).
 *  - `byNode`   pointer => `{context => entry}`; key "" is the root, carrying every
 *               class-level declaration (`list-item`, `summary`, `overview`, and the
 *               `#[RowActions]` list-column), each property name its per-context map.
 *
 * The per-context projection + validation is shared with {@see WidgetContextsStrategy}
 * via {@see WidgetContextProjector}.
 *
 * ## Reflection is keyed by CLASS; participation may be keyed by KEY
 *
 * `forResource()` reflects exactly ONE Data class, and one Data class may serve more than one
 * resource key — so a producer that adds participation per RESOURCE cannot express it through
 * the class alone. That mismatch is why context contributors exist (each a {@see ResourceContextContributor}
 * registered under a key in {@see ContextContributorRegistry}, frame-foundation 93) and why `forResource()` takes the
 * resource key (`ContextBlockFacts::$key`, frame-foundation 94) alongside its Data class: the reflection half stays
 * class-keyed, the contributed half is key-keyed, and the two merge here (particle-contribution-seam ticket 17
 * §A1/§A2).
 *
 * The registry arrives by NULLABLE CONSTRUCTOR ARGUMENT, never by service location. `new
 * ContextManifest` keeps working everywhere it already appears, a pure-frame host binds
 * nothing and gets an unchanged block, and the class stays testable with no container.
 *
 * The {@see WidgetContextProjector} arrives the same way (frame-foundation 89): by nullable constructor argument. The
 * container's manifest is given the container's projector; null means {@see WidgetContextProjector::resolved()} (the
 * container's when one is booted, else a plain one), so `new ContextManifest` inside an app projects exactly as the
 * container's does. This class never constructs a projector itself.
 */
class ContextManifest
{
    protected WidgetContextProjector $projector;

    public function __construct(
        protected ?ContextContributorRegistry $contributors = null,
        ?WidgetContextProjector $projector = null,
    ) {
        $this->projector = $projector ?? WidgetContextProjector::resolved();
    }

    /**
     * One resource's context block: `byNode` reflected from the Data class (plus the nodes contributed under the
     * resource key), `inherits` and `known` from the context registry, and the resource's and actor's facts from
     * `$facts` (frame-foundation 88/94). A caller with a definition passes
     * `forResource($definition->data, ContextBlockFacts::forDefinition($definition, $authorizer))`.
     *
     * The facts ride this block because a frame shell is handed its ContextManifest and never the definition. Each
     * one's meaning is on {@see ContextBlockFacts}; the actor's `can` and the resource's `createAffordance` are
     * deliberately NOT collapsed: a resource can be perfectly `creatable` and un-creatable BY YOU, and a shell renders
     * a create affordance when `createAffordance === 'frame' && can.create`.
     *
     * The default facts emit the block the Data class alone always got. The parameter is not nullable, so an old
     * positional call (`forResource(X::class, null, 'k')`) throws a TypeError rather than silently dropping its facts.
     * A new fact is a new {@see ContextBlockFacts} property, never a new parameter here.
     *
     * @param  class-string  $dataClass  the Data class reflected for `byNode`
     * @return array{byNode: array<string, array<string, mixed>>, inherits: array<string, list<string>>, known: list<string>, layout: 'single'|'subnav'|'master-detail'|null, createAffordance: 'frame'|'host', singularLabel: string, can: array{create?: bool, update?: bool, delete?: bool, actions?: array<string, bool>}, actions?: list<array<string, mixed>>, requires?: array<string, list<string>>, collections?: list<array<string, mixed>>}
     */
    public function forResource(string $dataClass, ContextBlockFacts $facts = new ContextBlockFacts): array
    {
        $key = $facts->key;
        $actions = $facts->actions;
        $requires = $facts->requires;
        $collections = $facts->collections;

        $reflection = new ReflectionClass($dataClass);
        $projector = $this->projector;

        $byNode = [];

        // Root ("") carries the class-level declarations (list-item / summary / overview /
        // row-actions).
        $rootMap = $projector->forClass($reflection);

        if (! empty($rootMap)) {
            $byNode[''] = $rootMap;
        }

        // Direct properties only — top-level-resource-scoped, no recursion.
        foreach ($reflection->getProperties() as $property) {
            $map = $projector->forProperty($property);

            if (! empty($map)) {
                $byNode[$property->getName()] = $map;
            }
        }

        // The plug half. Merged PER POINTER with reflection winning, so a contributed node can
        // never silently shadow one of the resource's own declarations. A contributed pointer
        // is dotted and a reflected property pointer is a bare name, so those two namespaces
        // cannot collide — but the root pointer "" is shared: a contributor returning "" would,
        // under a pointer-level merge, replace the reflected class-level map wholesale. Merging
        // one level down lets a contributor ADD a context at any pointer (including the root)
        // while a reflected context at the same pointer keeps the class's own entry.
        // The priority is now per-CONTEXT at EVERY pointer, not only at the root: reflection
        // wins each shared context key, where the earlier pointer-level merge let a contributed
        // pointer replace a reflected one wholesale.
        // frame-foundation 93: the contributed half is the registry's fold over every registered contributor (position
        // order, a later position winning a shared context); reflection still wins each context here. A null key calls
        // no factory.
        if ($this->contributors !== null && $key !== null) {
            foreach ($this->contributors->nodesFor($key) as $pointer => $contributed) {
                $byNode[$pointer] = array_merge($contributed, $byNode[$pointer] ?? []);
            }
        }

        return [
            'byNode' => $byNode,
            // frame-foundation 90: both from frame's `frame.contexts` registry, read through the projector this builder
            // was handed (89), in registration order. A host that registers nothing gets frame's seven and two edges.
            'inherits' => $projector->inherits(),
            'known' => $projector->known(),
            // The resource's declared inner-layout grammar (ticket 31) — the FrameLayout
            // socket's `variant` token. Handed in from the resource's ResourceDefinition
            // (the producer read it off its own declaration); frame no longer reflects a
            // producer attribute for it. Null when the resource is layout-agnostic; the
            // socket then falls back to SingleColumn (ticket 09).
            'layout' => $facts->layout,
            // WHERE this resource's create affordance lives — `'frame'` (its list Toolbar emits the
            // "New …" button) or `'host'` (the host's own chrome owns it, so frame emits none).
            // Already RESOLVED against the definition's `creatable` gate; the client reads this one
            // field and never recombines two, so `creatable` keeps a single spelling.
            'createAffordance' => $facts->createAffordance,
            // WHAT this resource calls ONE record — the noun frame's own toolbar puts after "New".
            // Already resolved (declared word, else the plural label inflected); the client neither
            // inflects nor sees the label, which is why the default toolbar said "New scaffold-packs".
            'singularLabel' => $facts->singularLabel,
            // WHETHER THIS ACTOR may write — the axis the client did not have, which is why the beam
            // console rendered a "New entry" button and 13 "Delete entry" buttons for a member
            // holding only `beam-ux-entry.view`. Resolved server-side against the same
            // ResourceAuthorizer the endpoint asks, so the button and its 403 cannot disagree.
            'can' => $facts->can,
            // WHAT this resource offers beyond CRUD (ADR-0005) — each action's button, form input and
            // result presentation, serialized the way the manifest's `resources[]` entry carries it.
            // Emitted only when there is something to say, so a resource without actions keeps the
            // exact block it had. Whether THIS ACTOR may press each one is `can.actions`, above.
            ...($actions === [] ? [] : [
                'actions' => array_map(
                    fn (ResourceActionDefinition $action): array => $action->toArray(),
                    array_values($actions),
                ),
            ]),
            // The agreement keys each behaviour requires (agreements 05), copied from the definition for the reason
            // `$layout` is: a shell reads this block, never the definition. Omitted when there are none, so a resource
            // without a Requirement keeps the exact block it had.
            ...($requires === [] ? [] : ['requires' => $requires]),
            ...($collections === [] ? [] : [
                'collections' => array_map(
                    fn (ResourceCollectionAddress $collection): array => $collection->toArray(),
                    array_values($collections),
                ),
            ]),
        ];
    }
}
