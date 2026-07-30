<?php

namespace Schemastud\Frame\Registry;

use ReflectionAttribute;
use ReflectionClass;
use Schemastud\Frame\Attributes\AdminResource;

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
 */
class ContextManifest
{
    /**
     * @return array{byNode: array<string, array<string, mixed>>, inherits: array<string, list<string>>, known: list<string>, layout: 'single'|'subnav'|'master-detail'|null}
     */
    public function forResource(string $dataClass): array
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

        return [
            'byNode' => $byNode,
            'inherits' => ['row-cell' => ['edit']],
            'known' => WidgetContextProjector::KnownContexts,
            // The resource's declared inner-layout grammar (ticket 31) — the FrameLayout
            // socket's `variant` token read off #[AdminResource(layout: …)]. Null when the
            // resource is layout-agnostic (no attribute, or `layout` unset); the socket then
            // falls back to SingleColumn (ticket 09). A fixture/DTO without the attribute
            // simply emits null — this stays a pure additive field.
            'layout' => $this->layoutFor($reflection),
        ];
    }

    /**
     * The `layout` the resource declares on its `#[AdminResource]` attribute, or null
     * when the class carries no attribute or leaves `layout` unset. Validated at the
     * attribute's constructor, so it is already one of {@see AdminResource::Layouts}.
     *
     * @param  ReflectionClass<object>  $reflection
     */
    private function layoutFor(ReflectionClass $reflection): ?string
    {
        $attributes = $reflection->getAttributes(AdminResource::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance()->layout;
    }
}
