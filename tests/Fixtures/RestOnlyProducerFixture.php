<?php

namespace Schemastud\Frame\Tests\Fixtures;

use InvalidArgumentException;
use Rushing\Popcorn\Registries\Authorizer;
use Rushing\Popcorn\Registries\Gated;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryKey;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\ResourceDefinition;

/**
 * A producer whose PORT half is narrower than its STORE — the shape
 * `Splicewire\Beam\Frame\ParticleResourceRegistryAdapter` has live, reproduced here because frame
 * must never import a beam type.
 *
 * Beam's real one holds `ParticleResource` DECLARATIONS at `beam.particle.resources` and projects
 * them into `ResourceDefinition`s on read; a REST-only particle resource exists in the store and has
 * no frame projection, so `has()` answers `false` for it while `unfiltered()->has()` answers `true`.
 * This fixture makes every framed answer empty and keeps the store's, which is the same asymmetry at
 * its limit — and it is what `CompositeResourceRegistryTest` uses to prove the index ROUTES rather
 * than re-deciding.
 */
class RestOnlyProducerFixture implements Gated, ResourceRegistry
{
    private InMemoryResourceRegistry $store;

    public function __construct(ResourceDefinition ...$definitions)
    {
        $this->store = new InMemoryResourceRegistry('rest_only');

        foreach ($definitions as $definition) {
            $this->store->register($definition);
        }
    }

    public function has(RegistryKey|string $key): bool
    {
        return false;
    }

    public function get(string $key): ResourceDefinition
    {
        throw new InvalidArgumentException("No frame resource registered for key [{$key}].");
    }

    public function find(string $key): ?ResourceDefinition
    {
        return null;
    }

    /** @return list<ResourceDefinition> */
    public function all(): array
    {
        return [];
    }

    public function register(RegistryKey|string $key, mixed $entry = null, ?string $by = null, ?string $ability = null): static
    {
        $this->store->register($key, $entry, $by, $ability);

        return $this;
    }

    public function resolve(RegistryKey|string $key): mixed
    {
        return $this->store->resolve($key);
    }

    public function tryResolve(RegistryKey|string $key): mixed
    {
        return $this->store->tryResolve($key);
    }

    /** @return list<mixed> */
    public function matches(RegistryKey|string $key): array
    {
        return $this->store->matches($key);
    }

    /** @return list<RegistryKey> */
    public function keys(): array
    {
        return $this->store->keys();
    }

    public function unfiltered(): Registry
    {
        return $this->store->unfiltered();
    }

    public function authorizeWith(?Authorizer $authorizer): static
    {
        $this->store->authorizeWith($authorizer);

        return $this;
    }
}
