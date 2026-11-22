<?php

namespace Schemastud\Frame\Registry;

use Closure;
use InvalidArgumentException;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\ForwardsToRegistryStore;
use Rushing\Popcorn\Registries\Gated;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\Key;
use Rushing\Popcorn\Registries\Laddered;
use Rushing\Popcorn\Registries\OnKeyDuplicate;
use Rushing\Popcorn\Registries\PopulationRequirement;
use Rushing\Popcorn\Registries\RecordsRegistrants;
use Rushing\Popcorn\Registries\RecordsSupersession;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryKey;
use Schemastud\Frame\Contracts\ResourceContextContributor;

/**
 * The `frame.context-contributors` registry (frame-foundation 93; the shape is decision 87's ruling): every package's
 * source of extra render-context nodes for a resource, one factory per key `frame.context-contributors.<package>.<name>`.
 *
 * **Who registers.** A package, under its OWN key, from its provider's `boot()` (frame binds this registry as a singleton
 * at `register()`): `app(ContextContributorRegistry::class)->register('beam.collections', static fn () =>
 * app(CollectionResourceContextNodes::class))`. The entry is a FACTORY, `Closure(): ResourceContextContributor`, called
 * on every {@see nodesFor()} read and never cached here: a contributor may read the request, so each package keeps it as
 * its own `scoped()` binding and the factory resolves that binding through the CURRENT container when called. A factory
 * must never capture a `$this->app` (or any instance) taken at boot: under Octane that is the base application, not the
 * request's sandbox (decision 87, ruling 3). It is an extension point, unlike ADR-0008's resource registry port (ADR-0011).
 *
 * **Who reads.** {@see ContextManifest}, handed this registry by constructor (frame's provider binds it; a container-
 * resolved builder gets it with no edit). `new ContextManifest` with no argument reads reflection only.
 *
 * **Order.** {@see rungs()}: `declared` › `contributed`, outermost first. Declarative only (popcorn's `Laddered`; the
 * kernel never climbs): the climb is the fold in {@see nodesFor()} and the merge in ContextManifest. Contributors fold in
 * POSITION order and a later position wins a context two contributors name at the same pointer, whole (no deep merge);
 * the resource's own reflected declaration wins over every contributor (ContextManifest). Position is registration
 * order, which is provider boot order. `Supersede`: a host that replaces a package's contributor by key answers from the
 * replaced entry's slot. A host reads the order and the registrants with `popcorn:keys frame.context-contributors`, and
 * what it replaced with `--supersessions`.
 *
 * @implements Registry<Closure(): ResourceContextContributor>
 */
#[IsRegistry(
    root: 'frame.context-contributors',
    entryType: Closure::class,
    onKeyDuplicate: OnKeyDuplicate::Supersede,
    populationRequirement: PopulationRequirement::Optional,
    description: 'Context contributors: each package\'s factory of extra render-context nodes for a resource, folded in position order (a later position wins; the resource\'s own declaration wins over all). Registered from boot().',
)]
class ContextContributorRegistry implements Gated, Laddered, RecordsRegistrants, RecordsSupersession, Registry
{
    use ForwardsToRegistryStore;

    /** The registry's root; a key is `<root>.<package>.<name>`. */
    public const ROOT = 'frame.context-contributors';

    /** @var BasicRegistry<Closure(): ResourceContextContributor> */
    private BasicRegistry $entries;

    public function __construct()
    {
        $this->entries = BasicRegistry::for($this);
    }

    /**
     * Register a contributor factory under a package's key, or supersede the one already there (it keeps that slot).
     * The key is relative to the root (`beam.collections`) or absolute (`frame.context-contributors.beam.collections`).
     *
     * @param  (Closure(): ResourceContextContributor)|null  $factory
     *
     * @throws InvalidArgumentException when the entry is not a Closure
     */
    public function register(RegistryKey|string $key, mixed $factory = null, ?string $by = null, ?string $ability = null): static
    {
        if (! $factory instanceof Closure) {
            throw new InvalidArgumentException(self::ROOT.' takes a Closure(): '.ResourceContextContributor::class.' factory; got '.get_debug_type($factory).'.');
        }

        $this->entries->register($this->keyFor($key), $factory, $by, $ability);

        return $this;
    }

    /**
     * The contributed nodes for a resource key: every factory called in position order, its contributor asked, and the
     * answers folded per pointer, a later position winning a shared context whole (decision 87, Q2's fold). An empty
     * registry answers `[]`.
     *
     * @return array<string, array<string, mixed>> pointer => context => map
     */
    public function nodesFor(string $key): array
    {
        $nodes = [];

        $store = $this->entries->unfiltered();
        foreach ($store->keys() as $at) {
            $factory = $store->resolve($at);
            $contributor = $factory();
            if (! $contributor instanceof ResourceContextContributor) {
                throw new InvalidArgumentException("The contributor factory at [{$at}] returned ".get_debug_type($contributor).', not a '.ResourceContextContributor::class.'.');
            }

            foreach ($contributor->nodesFor($key) as $pointer => $contexts) {
                $nodes[$pointer] = array_merge($nodes[$pointer] ?? [], $contexts);
            }
        }

        return $nodes;
    }

    /** @return list<string> outermost first: the resource's own declaration shadows every contributor */
    public function rungs(): array
    {
        return ['declared', 'contributed'];
    }

    public function registrantOf(RegistryKey|string $key): ?string
    {
        return $this->entries->registrantOf($this->keyFor($key));
    }

    /** @return list<RegistryKey> */
    public function keysBy(string $registrant): array
    {
        return $this->entries->keysBy($registrant);
    }

    public function superseded(RegistryKey|string $key): array
    {
        return $this->entries->superseded($this->keyFor($key));
    }

    private function keyFor(RegistryKey|string $key): RegistryKey
    {
        $key = (string) $key;

        return Key::parse(str_starts_with($key, self::ROOT.'.') ? $key : self::ROOT.'.'.$key);
    }

    protected function store(): BasicRegistry
    {
        return $this->entries;
    }
}
