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
 *  - `known`    the closed five-context enum.
 *  - `inherits` static inheritance edges (`row-cell` falls back to `edit`).
 *  - `byNode`   pointer => `{context => entry}`; key "" is the root (class-level
 *               `#[WidgetIn('list-item')]` / `#[RowActions]`), each property name its
 *               per-context map.
 *
 * The per-context projection + validation is shared with {@see WidgetContextsStrategy}
 * via {@see WidgetContextProjector}.
 *
 * ## Reflection is keyed by CLASS; participation may be keyed by KEY
 *
 * `forResource()` reflects exactly ONE Data class, and one Data class may serve more than one
 * resource key — so a producer that adds participation per RESOURCE cannot express it through
 * the class alone. That mismatch is why the optional {@see ResourceContextContributor} port
 * exists and why `forResource()` takes the resource `$key` alongside its Data class: the
 * reflection half stays class-keyed, the plug half is key-keyed, and the two merge here
 * (particle-contribution-seam ticket 17 §A1/§A2).
 *
 * The port arrives by NULLABLE CONSTRUCTOR ARGUMENT, never by service location. `new
 * ContextManifest` keeps working everywhere it already appears, a pure-frame host binds
 * nothing and gets an unchanged block, and the class stays testable with no container.
 */
class ContextManifest
{
    public function __construct(
        protected ?ResourceContextContributor $contributor = null,
    ) {}

    /**
     * @param  'single'|'subnav'|'master-detail'|null  $layout  the resource's declared inner-layout grammar, handed in from its {@see ResourceDefinition} (frame no longer reflects the producer's attribute for it)
     * @param  string|null  $key  the resource's registry key, for the {@see ResourceContextContributor} plug. Null (or no bound port) ⇒ reflection only, which is every pure-frame host.
     * @param  'frame'|'host'  $createAffordance  the RESOLVED create affordance, handed in from the resource's {@see ResourceDefinition::resolvedCreateAffordance()}. It rides this block rather than the definition for the same reason `$layout` does: a frame shell is handed its ContextManifest and never the definition, so a presentation fact the shell must read has to arrive here. Defaults to `'frame'` — today's behaviour — so every existing caller of this method emits an unchanged block.
     * @param  string  $singularLabel  the RESOLVED display singular, handed in from {@see ResourceDefinition::resolvedSingularLabel()}. It rides this block for the same reason `$layout` and `$createAffordance` do: a frame shell is handed its ContextManifest and never the definition, so a presentation fact the shell must read has to arrive here. Empty (the default) ⇒ the shell falls back to the resource KEY, i.e. today's behaviour, so every existing caller of this method emits an unchanged block.
     * @return array{byNode: array<string, array<string, mixed>>, inherits: array<string, list<string>>, known: list<string>, layout: 'single'|'subnav'|'master-detail'|null, createAffordance: 'frame'|'host', singularLabel: string}
     */
    public function forResource(string $dataClass, ?string $layout = null, ?string $key = null, string $createAffordance = 'frame', string $singularLabel = ''): array
    {
        $reflection = new ReflectionClass($dataClass);
        $projector = new WidgetContextProjector;

        $byNode = [];

        // Root ("") carries the class-level declarations (list-item / row-actions).
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

        // The plug half. Merged AFTER reflection so a contributed pointer can never silently
        // shadow one of the resource's own properties — a contributed pointer is dotted and a
        // reflected one is a bare property name, so the two namespaces cannot collide, but the
        // ordering makes that an invariant rather than a coincidence.
        if ($this->contributor !== null && $key !== null) {
            $byNode = array_merge($byNode, $this->contributor->nodesFor($key));
        }

        return [
            'byNode' => $byNode,
            'inherits' => ['row-cell' => ['edit']],
            'known' => WidgetContextProjector::KnownContexts,
            // The resource's declared inner-layout grammar (ticket 31) — the FrameLayout
            // socket's `variant` token. Handed in from the resource's ResourceDefinition
            // (the producer read it off its own declaration); frame no longer reflects a
            // producer attribute for it. Null when the resource is layout-agnostic; the
            // socket then falls back to SingleColumn (ticket 09).
            'layout' => $layout,
            // WHERE this resource's create affordance lives — `'frame'` (its list Toolbar emits the
            // "New …" button) or `'host'` (the host's own chrome owns it, so frame emits none).
            // Already RESOLVED against the definition's `creatable` gate; the client reads this one
            // field and never recombines two, so `creatable` keeps a single spelling.
            'createAffordance' => $createAffordance,
            // WHAT this resource calls ONE record — the noun frame's own toolbar puts after "New".
            // Already resolved (declared word, else the plural label inflected); the client neither
            // inflects nor sees the label, which is why the default toolbar said "New scaffold-packs".
            'singularLabel' => $singularLabel,
        ];
    }
}
