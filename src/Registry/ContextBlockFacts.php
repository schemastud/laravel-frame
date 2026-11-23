<?php

namespace Schemastud\Frame\Registry;

use Schemastud\Frame\Authorization\ResourceAuthorizer;
use Spatie\LaravelData\Optional;

/**
 * The resource- and actor-level facts a context block carries beside `byNode`, `inherits` and `known`
 * (frame-foundation 88/94). {@see ContextManifest::forResource()} takes the Data class and one of these; each fact
 * rides the block because a frame shell is handed its ContextManifest and never the definition.
 *
 * Build it with {@see self::forDefinition()}: frame's manifest controller, tower's, and {@see
 * \Schemastud\Frame\Vocabulary\DeclaredWidgets} all do, and no `src/` caller builds one by hand. The constructor's
 * named defaults are the block's defaults, so `new ContextBlockFacts` (what `forResource` defaults to) emits the
 * block a Data class alone always got, and a test may name only the facts it reads (`new ContextBlockFacts(key: 'x')`).
 *
 * It is an in-process value, not a Data class: what crosses the wire is the array `forResource` returns, whose
 * contract is {@see \Schemastud\Frame\Wire\EmittedWireKeys}.
 *
 * ## Adding a fact (88, ruling 5)
 *
 * A new block fact is one new constructor property here, whose default emits what the block emits without it; {@see
 * self::forDefinition()} fills it from the definition; `forResource` emits it; and `EmittedWireKeys` lists it with its
 * reader in the same change (CONFORM 05-11). No caller's signature changes. The set is closed: only laravel-frame
 * adds a fact. A host or package that wants more on the block contributes nodes through
 * {@see ContextContributorRegistry}, or a context through {@see WidgetContextRegistry}.
 */
final class ContextBlockFacts
{
    /**
     * The agreement keys each behaviour requires (agreements 05), never an empty list: the constructor normalises
     * whatever it is handed through {@see ResourceDefinition::requiresFor()} (83's rule, held by every producer), so
     * `requires: ['x:read' => []]` emits no `requires` key.
     *
     * @var array<string, list<string>>
     */
    public readonly array $requires;

    /**
     * @param  'single'|'subnav'|'master-detail'|null  $layout  the resource's declared inner-layout grammar
     * @param  string|null  $key  the resource's registry key: the {@see ContextContributorRegistry} is asked by it. Null ⇒ reflection only
     * @param  'frame'|'host'  $createAffordance  the RESOLVED create affordance ({@see ResourceDefinition::resolvedCreateAffordance()})
     * @param  string  $singularLabel  the RESOLVED display singular; empty ⇒ the shell falls back to the key
     * @param  array{create?: bool, update?: bool, delete?: bool, actions?: array<string, bool>}  $can  the CURRENT ACTOR's capabilities; empty ⇒ no actor axis was resolved (permissive by design: the endpoint refuses the write)
     * @param  list<ResourceActionDefinition>  $actions  the resource's declared actions (ADR-0005); empty ⇒ no `actions` key
     * @param  list<ResourceCollectionAddress>  $collections  the actor's readable Collection choices; empty ⇒ no `collections` key
     * @param  array<string, list<string>>  $requires  the `requires` behaviour map; normalised, see {@see self::$requires}
     */
    public function __construct(
        public readonly ?string $layout = null,
        public readonly ?string $key = null,
        public readonly string $createAffordance = 'frame',
        public readonly string $singularLabel = '',
        public readonly array $can = [],
        public readonly array $actions = [],
        public readonly array $collections = [],
        array $requires = [],
    ) {
        $normalised = ResourceDefinition::requiresFor($requires);
        $this->requires = $normalised instanceof Optional ? [] : $normalised;
    }

    /**
     * The facts of one resource's block, read off its definition (whose `collections` already hold the actor's
     * readable ones), with `can` composed from the actor's authorizer: its capabilities, plus `actions` when the
     * resource declares any. No authorizer ⇒ `can` is `[]`, the "no actor axis" default.
     */
    public static function forDefinition(ResourceDefinition $definition, ?ResourceAuthorizer $authorizer = null): self
    {
        return new self(
            layout: $definition->layout,
            key: $definition->key,
            createAffordance: $definition->resolvedCreateAffordance(),
            singularLabel: $definition->resolvedSingularLabel(),
            // The ACTOR axis. A block built with an authorizer VARIES BY USER: a host caching the manifest must key
            // that cache on the viewer. An anonymous request resolves every capability false.
            can: $authorizer === null ? [] : [
                ...$authorizer->capabilities($definition),
                // The per-actor half of the resource's declared ACTIONS (ADR-0005). Absent for a resource that
                // declares none.
                ...($definition->actions === [] ? [] : ['actions' => $authorizer->actionCapabilities($definition)]),
            ],
            actions: $definition->actions,
            collections: $definition->collections,
            requires: $definition->requires instanceof Optional ? [] : $definition->requires,
        );
    }
}
