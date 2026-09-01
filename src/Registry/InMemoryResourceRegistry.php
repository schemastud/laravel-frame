<?php

namespace Schemastud\Frame\Registry;

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
 * The agnostic default {@see ResourceRegistry}: a keyed store of key ->
 * {@see ResourceDefinition}. It holds definitions a producer HANDS it via
 * {@see self::register()}; it knows nothing about attributes, discovery, or any
 * particular opinion about what a resource IS — that is the producer's concern (a
 * CMS engine reflects declarations and feeds definitions in).
 *
 * A host that binds no producer can bind this directly and register definitions
 * imperatively; a host with a producer binds the producer's registry to the same port.
 *
 * ## Declared, as of registry-kernel 38's sweep
 *
 * The private `array $resources` is gone; the keyspace is a composed {@see BasicRegistry}, which is
 * what makes `frame.resources` addressable through the index and `popcorn:keys` instead of only
 * through this class. The port's own vocabulary — {@see get()}, {@see find()}, {@see all()} — stays
 * as sugar over the kernel's, with its ORIGINAL miss behaviour: `get()` still throws
 * {@see InvalidArgumentException} with the same sentence, because a consumer may be catching it and
 * the kernel's `RegistryMiss` is a different type carrying a different one.
 *
 * `Optionality::Optional` is the honest declaration: frame's whole design allows a host that binds
 * no producer, whose manifest route resolves an empty registry and serves `{resources: []}`.
 */
#[IsRegistry(
    root: 'frame.resources',
    of: 'frame resource definitions — one editor wiring (data class, layout, columns, widgets) per resource key',
    arity: RegistryArity::PickOne,
    entryType: ResourceDefinition::class,
    onDuplicate: OnDuplicate::Supersede,
    optionality: Optionality::Optional,
    note: 'Supersede is what this class has always done — registration was a plain array assignment '
        .'under $definition->key and the docblock called it "idempotent by key", so a second '
        .'registration replaced the first silently. Declaring it makes the displaced definition '
        .'readable rather than lost. This is the AGNOSTIC default implementation of frame\'s port; a '
        .'host binding a producer (beam aliases the port onto a forwarder over '
        .'`beam.particle.resources`) never constructs this, and the root simply stands empty there.',
    order: 30,
)]
class InMemoryResourceRegistry implements Gated, ResourceRegistry
{
    /** @var BasicRegistry<ResourceDefinition> */
    private BasicRegistry $entries;

    public function __construct()
    {
        $this->entries = BasicRegistry::for($this);
    }

    /**
     * Add (or overwrite by key) one definition. Idempotent by key.
     *
     * WIDENED contravariantly from `register(ResourceDefinition $definition)`: the self-keying
     * one-argument call every historical producer makes keeps working unchanged, and the kernel's own
     * `register('plans', $definition, by: …)` spelling now works too — which is what lets a
     * {@see \Rushing\Popcorn\Registries\Registrar} fill this at all.
     *
     * @param  RegistryKey|string|ResourceDefinition  $key  the resource key, or the self-keying definition
     * @param  ResourceDefinition|null  $entry  the definition when `$key` is a key; ignored otherwise
     */
    public function register(RegistryKey|string|ResourceDefinition $key, mixed $entry = null, ?string $by = null, ?string $ability = null): static
    {
        if ($key instanceof ResourceDefinition) {
            $entry = $key;
            $key = $key->key;
        }

        if (! $entry instanceof ResourceDefinition) {
            throw new InvalidArgumentException(sprintf(
                'InMemoryResourceRegistry stores ResourceDefinition entries; `%s` was given for key [%s].',
                get_debug_type($entry),
                (string) $key,
            ));
        }

        $this->entries->register($key, $entry, $by, $ability);

        return $this;
    }

    /**
     * Whether a definition is registered under `$key`.
     *
     * Gated on {@see Key::tryParse()} rather than delegating bare: this is the read
     * {@see \Schemastud\Frame\Http\Controllers\FrameResourceController} makes with a raw URL segment,
     * and `Key::parse()` throws on an illegal one BEFORE any miss is considered. A 500 where the
     * caller's whole job is to answer "no" is a regression, and relaxing the parser to avoid it is
     * refused at the kernel.
     */
    public function has(RegistryKey|string $key): bool
    {
        if (is_string($key) && Key::tryParse($key) === null) {
            return false;
        }

        return $this->entries->has($key);
    }

    public function resolve(RegistryKey|string $key): mixed
    {
        return $this->entries->resolve($key);
    }

    public function tryResolve(RegistryKey|string $key): mixed
    {
        return $this->entries->tryResolve($key);
    }

    /** @return list<ResourceDefinition> */
    public function matches(RegistryKey|string $key): array
    {
        return $this->entries->matches($key);
    }

    /** @return list<RegistryKey> */
    public function keys(): array
    {
        return $this->entries->keys();
    }

    public function unfiltered(): Registry
    {
        return $this->entries->unfiltered();
    }

    public function authorizeWith(?Authorizer $authorizer): static
    {
        $this->entries->authorizeWith($authorizer);

        return $this;
    }

    /**
     * The definition under `$key`.
     *
     * @throws InvalidArgumentException unchanged in type, message and condition from before this class
     *                                  conformed — the kernel's `RegistryMiss` is deliberately not
     *                                  what escapes here.
     */
    public function get(string $key): ResourceDefinition
    {
        return $this->find($key) ?? throw new InvalidArgumentException(
            "No frame resource registered for key [{$key}]."
        );
    }

    /** The same lookup, `null` on a miss or on a string that is not a legal registry key. */
    public function find(string $key): ?ResourceDefinition
    {
        if (Key::tryParse($key) === null) {
            return null;
        }

        return $this->entries->tryResolve($key);
    }

    /**
     * The served manifest: flat sibling entries, insertion order.
     *
     * Built from `relativeKeys()` rather than `keys()` — keys go relative in and absolute out, and a
     * caller-facing enumeration wants the caller's spelling, not `frame.resources.plans`.
     *
     * @return list<ResourceDefinition>
     */
    public function all(): array
    {
        $manifest = [];

        foreach ($this->entries->relativeKeys() as $key) {
            $definition = $this->entries->tryResolve($key);

            if ($definition instanceof ResourceDefinition) {
                $manifest[] = $definition;
            }
        }

        return $manifest;
    }
}
