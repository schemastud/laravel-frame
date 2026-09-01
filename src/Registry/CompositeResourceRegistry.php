<?php

namespace Schemastud\Frame\Registry;

use Closure;
use InvalidArgumentException;
use Rushing\Popcorn\Registries\Authorizer;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\Gated;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\Key;
use Rushing\Popcorn\Registries\OnDuplicate;
use Rushing\Popcorn\Registries\Optionality;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryArity;
use Rushing\Popcorn\Registries\RegistryKey;
use Schemastud\Frame\Contracts\ResourceRegistry;

/**
 * `frame.resources` — the registry of resource REGISTRIES, one entry per producer.
 *
 * Registry-kernel ticket 77's ruling. Before it, `frame.resources` was declared on
 * {@see InMemoryResourceRegistry}, frame's default concrete — and at every host that binds a producer
 * the container's answer for the port and the index's answer for the root were **two different
 * objects**: `app(ResourceRegistry::class)` was the producer's registry holding 53 resources
 * (`~/Herd/splicewire-app`) while `ownerOf('frame.resources')` was a freshly-constructed
 * `InMemoryResourceRegistry` holding none. The root read `0 entries` and meant *"ask somewhere else"*,
 * which is not what an empty root looks like anywhere else in the estate.
 *
 * This class is what the port BINDS to, so the two answers are one object again, and the empty root
 * becomes an index whose entries are the things actually answering.
 *
 * ## Why an index and not a second root over the same resources
 *
 * `Splicewire\Beam\Frame\ParticleResourceRegistryAdapter`'s docblock refused to declare
 * `#[IsRegistry]` on the grounds that *"a second root over the same entries would be two registries
 * claiming one set of keys — the root collision the attribute exists to make detectable, manufactured
 * on purpose."* That is correct for the shape it was choosing between, and it dissolves here: **an
 * index's entries are registries, not resources**, so `frame.resources.beam` names beam's *registry*
 * and collides with nothing. Ticket 26 D1 already ruled registry-as-entry legal generally — the
 * kernel's own `RegistryIndex` is a registry whose entries are registries — so no kernel change was
 * needed, and none was made.
 *
 * ## The two hats, and where the seam between them falls
 *
 * This class wears the same two hats {@see ResourceRegistry} itself does, and the split is the
 * opposite way round from every other implementer, so it is worth stating plainly:
 *
 * - The **kernel half** — {@see keys()}, {@see resolve()}, {@see tryResolve()}, {@see matches()} — is
 *   over MEMBERS. `keys()` returns `frame.resources.beam`, and `resolve()` hands back the registry.
 *   This is `RegistryIndex`'s own shape.
 * - The **port half** — {@see has()}, {@see get()}, {@see find()}, {@see all()} — is over RESOURCES and
 *   fans out to the members, **delegating rather than reimplementing**. Each member's own projection
 *   semantics therefore still apply, which is the one thing ticket 77 forbids flattening: a REST-only
 *   particle resource is `has() === false` through beam's adapter and `true` through its
 *   `unfiltered()`, and that stays true read through this class.
 *
 * ⚠️ **So `has($this->keys()[0])` is `false`, deliberately.** `has()` is the port's question — *"is
 * there a framed resource under this key"* — because that is what {@see ResourceRegistry::has()}
 * declares for every implementer and what `Schemastud\Frame\Http\Controllers\FrameResourceController`
 * calls with a raw URL segment. Membership is asked with {@see keys()}/{@see resolve()}, never with
 * `has()`. `ResourceRegistryConformanceTest` pins both halves so the asymmetry cannot drift into an
 * accident.
 *
 * ## Resolution order: the LAST member attached wins
 *
 * Two producers holding one resource key is legal — that is the third thing ticket 77 set out to fix,
 * because the container `alias()` this replaces resolved it by one binding silently overwriting
 * another with load order deciding and nothing recording it. Here both members are entries, both are
 * enumerable, and a keyed read consults them in **reverse attachment order**: the last attached member
 * that can answer wins, and the displaced one is still readable at its own member key. That direction
 * is `OnDuplicate::Supersede`'s and it is what the alias did, so nothing changes meaning — it just
 * stops being invisible.
 *
 * The declared arity is a two-step list, outermost first, and it is the honest reading of the above:
 * `RunAll` over the members (an `all()` engages every one of them), then `PickOne` inside whichever
 * member answers.
 */
#[IsRegistry(
    root: 'frame.resources',
    of: 'resource registries — one entry per PRODUCER of frame resource definitions (frame\'s own imperative store, a CMS engine\'s, a host\'s)',
    arity: [RegistryArity::RunAll, RegistryArity::PickOne],
    entryType: ResourceRegistry::class,
    onDuplicate: OnDuplicate::Supersede,
    optionality: Optionality::Optional,
    note: 'The entries are REGISTRIES, not resource definitions — a resource key is answered by routing '
        .'to a member and letting that member project it, never by this class holding definitions of '
        .'its own. Optional because frame\'s whole design allows a host that attaches no producer, '
        .'whose manifest route then serves `{resources: []}`. Registry-kernel 77.',
    order: 30,
)]
class CompositeResourceRegistry implements Gated, ResourceRegistry
{
    /**
     * The member key frame's own {@see InMemoryResourceRegistry} is attached under, and the target of
     * {@see register()} — the one write this class forwards rather than routes.
     */
    public const DEFAULT_MEMBER = 'frame';

    /** @var BasicRegistry<ResourceRegistry|Closure(): ResourceRegistry> */
    private BasicRegistry $members;

    private ?Authorizer $authorizer = null;

    /** Set only by {@see unfiltered()}: every member this view hands back is unfiltered too. */
    private bool $deep = false;

    public function __construct()
    {
        $this->members = BasicRegistry::for($this);
    }

    /**
     * Take a producer's registry into the index under `$member`.
     *
     * `$member` is a single, author-chosen, {@see Key}-legal segment — `beam`, `frame`, a host's own
     * name — and NOT the producing package's composer coordinate. Ticket 73 D7 refused to bake that
     * coordinate for *provenance* on the grounds it had no consumer; this is an *address*, a different
     * question with a different answer, and an address has to be short, stable and typeable at a shell.
     * Provenance is unaffected and still available: pass `$by`, and {@see \Rushing\Popcorn\Registries\RecordsRegistrants}
     * reads it back.
     *
     * The registry may be given as a {@see Closure}, and a producer should prefer that: membership then
     * costs one array write at boot and the producer's registry is constructed on first read, which is
     * the same laziness `RegistryIndex::describeLazily()` exists for.
     *
     * @param  ResourceRegistry|Closure(): ResourceRegistry  $registry
     */
    public function attach(string $member, ResourceRegistry|Closure $registry, ?string $by = null): static
    {
        $this->members->register($member, $registry, $by);

        return $this;
    }

    /** The declaration this class carries, read the way {@see BasicRegistry} reads it. */
    public function declaration(): IsRegistry
    {
        return $this->members->declaration();
    }

    /**
     * Every attached member, resolved, in ATTACHMENT order — the enumeration the fan-out below reverses.
     *
     * @return list<ResourceRegistry>
     */
    public function producers(): array
    {
        return array_map(
            fn (RegistryKey $key): ResourceRegistry => $this->reveal($this->store()->resolve($key)),
            $this->store()->keys(),
        );
    }

    // ---------------------------------------------------------------------------------------------
    // The PORT half — resources. Every one of these delegates; none of them projects.
    // ---------------------------------------------------------------------------------------------

    /**
     * Whether ANY member answers for `$key` — the port's question, not the kernel's.
     *
     * See the class docblock: this is `false` for a member key and `true` for a framed resource, which
     * is the inverse of {@see keys()} and is deliberate.
     */
    public function has(RegistryKey|string $key): bool
    {
        if (is_string($key) && Key::tryParse($key) === null) {
            return false;
        }

        foreach ($this->reversed() as $member) {
            if ($this->readable($member)->has($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws InvalidArgumentException unchanged in type, message and condition from
     *                                  {@see InMemoryResourceRegistry::get()} — a consumer may be
     *                                  catching it, and the kernel's `RegistryMiss` is a different type
     *                                  carrying a different sentence.
     */
    public function get(string $key): ResourceDefinition
    {
        return $this->find($key) ?? throw new InvalidArgumentException(
            "No frame resource registered for key [{$key}]."
        );
    }

    /** The last-attached member that can answer, or `null`. */
    public function find(string $key): ?ResourceDefinition
    {
        if (Key::tryParse($key) === null) {
            return null;
        }

        foreach ($this->reversed() as $member) {
            $definition = $member->find($key);

            if ($definition instanceof ResourceDefinition) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * The served manifest: every member's own `all()`, in attachment order, with a later member's
     * definition superseding an earlier one under the same key IN PLACE — so a host reading the
     * manifest sees stable ordering whichever producer happens to own a given row.
     *
     * @return list<ResourceDefinition>
     */
    public function all(): array
    {
        $manifest = [];

        foreach ($this->producers() as $member) {
            foreach ($member->all() as $definition) {
                $manifest[$definition->key] = $definition;
            }
        }

        return array_values($manifest);
    }

    // ---------------------------------------------------------------------------------------------
    // The KERNEL half — members.
    // ---------------------------------------------------------------------------------------------

    /**
     * Write a definition through the DEFAULT member.
     *
     * A producer fills its OWN registry by its own machinery; the only imperative write frame itself
     * offers is into {@see InMemoryResourceRegistry}, so that is where this lands. It keeps the
     * historical `app(ResourceRegistry::class)->register($definition)` spelling working at a
     * frame-only host, and it is the one method here that forwards rather than routes.
     *
     * @param  RegistryKey|string|ResourceDefinition  $key  the resource key, or the self-keying definition
     */
    public function register(RegistryKey|string|ResourceDefinition $key, mixed $entry = null, ?string $by = null, ?string $ability = null): static
    {
        $this->defaultMember()->register($key, $entry, $by, $ability);

        return $this;
    }

    /** @return list<RegistryKey> the MEMBER keys — `frame.resources.beam`, not `frame.resources.plans`. */
    public function keys(): array
    {
        return $this->store()->keys();
    }

    public function resolve(RegistryKey|string $key): mixed
    {
        return $this->reveal($this->store()->resolve($key));
    }

    public function tryResolve(RegistryKey|string $key): mixed
    {
        $entry = $this->store()->tryResolve($key);

        return $entry === null ? null : $this->reveal($entry);
    }

    /** @return list<ResourceRegistry> */
    public function matches(RegistryKey|string $key): array
    {
        return array_map(
            fn (mixed $entry): ResourceRegistry => $this->reveal($entry),
            $this->store()->matches($key),
        );
    }

    /**
     * A DEEP unfiltered view: the members it hands back, and the members its port half asks, are
     * unfiltered too.
     *
     * One level would be a lie here — the whole reason a caller reaches for this is to get past a
     * member's own gate, and `RegistryIndex::unfiltered()` was made deep for exactly that reason
     * (registry-kernel 45).
     */
    public function unfiltered(): Registry
    {
        if ($this->deep) {
            return $this;
        }

        $view = clone $this;
        $view->deep = true;

        return $view;
    }

    public function authorizeWith(?Authorizer $authorizer): static
    {
        $this->authorizer = $authorizer;
        $this->members->authorizeWith($authorizer);

        foreach ($this->producers() as $member) {
            if ($member instanceof Gated) {
                $member->authorizeWith($authorizer);
            }
        }

        return $this;
    }

    // ---------------------------------------------------------------------------------------------

    /** @return BasicRegistry<ResourceRegistry|Closure(): ResourceRegistry> */
    private function store(): BasicRegistry
    {
        /** @var BasicRegistry<ResourceRegistry|Closure(): ResourceRegistry> $store */
        $store = $this->deep ? $this->members->unfiltered() : $this->members;

        return $store;
    }

    /**
     * Members in REVERSE attachment order — the fan-out order for a keyed read, where the last member
     * attached wins. See the class docblock.
     *
     * @return list<ResourceRegistry>
     */
    private function reversed(): array
    {
        return array_reverse($this->producers());
    }

    private function defaultMember(): ResourceRegistry
    {
        $member = $this->store()->tryResolve(self::DEFAULT_MEMBER);

        if ($member === null) {
            $this->attach(self::DEFAULT_MEMBER, new InMemoryResourceRegistry(self::DEFAULT_MEMBER));

            $member = $this->store()->resolve(self::DEFAULT_MEMBER);
        }

        return $this->reveal($member);
    }

    /**
     * A member as this VIEW reads it — the member itself, or its own `unfiltered()` under a deep view.
     *
     * Typed {@see Registry} rather than {@see ResourceRegistry} on purpose: beam's adapter unfilters to
     * `Splicewire\Beam\Particle\ParticleResourceRegistry`, which is a kernel registry and deliberately
     * not a frame port — that is exactly the store-vs-projection asymmetry ticket 77 forbids flattening.
     * So only {@see has()} and the kernel half read through here; the port's projection methods always
     * ask the member itself.
     */
    private function readable(ResourceRegistry $member): Registry
    {
        return $this->deep ? $member->unfiltered() : $member;
    }

    /**
     * Resolve a stored entry to a live registry — invoking the closure a lazy producer attached.
     *
     * Not memoised, and it does not need to be: a producer's closure is `fn () => $app->make(...)` over
     * a container SINGLETON, so identity is stable and the second call is an array read. Memoising here
     * would instead pin the first-resolved instance past a host rebinding it, which is the failure this
     * whole ticket is about.
     */
    private function reveal(mixed $entry): ResourceRegistry
    {
        if ($entry instanceof Closure) {
            $entry = $entry();
        }

        if (! $entry instanceof ResourceRegistry) {
            throw new InvalidArgumentException(sprintf(
                'frame.resources holds ResourceRegistry members; `%s` was attached.',
                get_debug_type($entry),
            ));
        }

        if ($this->authorizer !== null && $entry instanceof Gated) {
            $entry->authorizeWith($this->authorizer);
        }

        return $entry;
    }
}
