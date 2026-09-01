<?php

namespace Schemastud\Frame\Contracts;

use InvalidArgumentException;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryKey;
use Schemastud\Frame\Http\Controllers\FrameManifestController;
use Schemastud\Frame\Http\Controllers\FrameResourceController;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\ResourceDefinition;

/**
 * Frame's resource registry PORT — the manifest source frame's own machinery reads.
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
 *
 * ## It EXTENDS the kernel registry rather than duplicating it (registry-kernel 34 D4)
 *
 * This interface used to spell its own `has(string $key): bool` beside `get()` and `all()` — a
 * narrower, three-method re-statement of a contract the estate already has. It now extends
 * {@see Registry}, so a frame resource table is addressable as a registry (and bakeable into the
 * index) wherever one is expected, and the two domain verbs that are NOT the kernel's stay, because
 * they are the vocabulary every consumer speaks.
 *
 * **The entry type is deliberately un-narrowed.** `Registry<mixed>`, not
 * `Registry<ResourceDefinition>`: what an implementer STORES is the producer's own declaration shape,
 * and {@see get()}/{@see all()} are the PROJECTION of that shape into a {@see ResourceDefinition}.
 * {@see InMemoryResourceRegistry} happens to store the definition itself, so its two coincide and its
 * own `#[IsRegistry]` says `entryType: ResourceDefinition::class`; `Splicewire\Beam\Frame\ParticleResourceRegistryAdapter`
 * forwards onto a registry of `ParticleResource` declarations and projects on read. Declaring
 * `ResourceDefinition` here would make the second one a liar at the kernel seam, which is the one
 * place the estate reads entry types mechanically.
 *
 * ## No `#[IsRegistry]` here, on purpose
 *
 * A declaration on an interface governs the interface and never its implementers, so a contract
 * handing ONE root to every implementer manufactures exactly the root collision the attribute exists
 * to make detectable — and `Rushing\Popcorn\Baking\DeclaredRegistryScan` skips interfaces for that
 * reason. The root is declared on the concrete the container binds. Frame's own is
 * {@see InMemoryResourceRegistry} (`frame.resources`); a producer's concrete declares its own, or
 * declares none where it is a stateless forwarder onto a keyspace something else already owns. Same
 * ruling `Schemastud\Blockdoc\Contracts\Schema` records for the node-type table.
 *
 * ## `get()` throws and `find()` does not — both halves, deliberately
 *
 * The pair is Laravel's `findOrFail()`/`find()` split, and it is required of any port wrapping the
 * kernel (see {@see Registry::tryResolve()}). `get()` kept the exception it has always thrown — an
 * {@see InvalidArgumentException}, NOT the kernel's `RegistryMiss` — because a consumer may be
 * catching it; `find()` is the half that was missing, and it is what a caller holding a key it took
 * off a URL should reach for.
 *
 * @extends Registry<mixed>
 */
interface ResourceRegistry extends Registry
{
    /**
     * WIDENED from the three-method port this used to be: `has(string $key)` cannot narrow
     * {@see Registry::has()}, and every historical string call keeps working unchanged.
     *
     * A key that is not {@see \Rushing\Popcorn\Registries\Key}-legal at all — an uppercase or
     * slash-bearing URL segment — answers `false` here rather than raising `InvalidRegistryKey`.
     * "Not a legal key" and "no such key" are the same 404 to a caller asking this question.
     */
    public function has(RegistryKey|string $key): bool;

    /**
     * The definition registered under `$key`.
     *
     * @throws InvalidArgumentException no such resource — unchanged from before this port conformed.
     */
    public function get(string $key): ResourceDefinition;

    /**
     * The same lookup, `null` on a miss — including for a string that is not a legal registry key.
     *
     * The nullable twin {@see get()} never had. A host resolving a resource key off a request has
     * nowhere to go without it but catching an exception it never imported.
     */
    public function find(string $key): ?ResourceDefinition;

    /**
     * The served manifest: flat sibling entries, insertion order.
     *
     * @return list<ResourceDefinition>
     */
    public function all(): array;
}
