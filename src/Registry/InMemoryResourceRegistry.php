<?php

namespace Schemastud\Frame\Registry;

use InvalidArgumentException;
use Schemastud\Frame\Contracts\ResourceRegistry;

/**
 * The agnostic default {@see ResourceRegistry}: a plain in-memory map of key ->
 * {@see ResourceDefinition}. It holds definitions a producer HANDS it via
 * {@see self::register()}; it knows nothing about attributes, discovery, or any
 * particular opinion about what a resource IS — that is the producer's concern (a
 * CMS engine reflects declarations and feeds definitions in).
 *
 * A host that binds no producer can bind this directly and register definitions
 * imperatively; a host with a producer binds the producer's registry to the same port.
 */
class InMemoryResourceRegistry implements ResourceRegistry
{
    /** @var array<string, ResourceDefinition> */
    protected array $resources = [];

    /**
     * Add (or overwrite by key) one definition. Idempotent by key.
     */
    public function register(ResourceDefinition $definition): self
    {
        $this->resources[$definition->key] = $definition;

        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->resources[$key]);
    }

    public function get(string $key): ResourceDefinition
    {
        return $this->resources[$key] ?? throw new InvalidArgumentException(
            "No frame resource registered for key [{$key}]."
        );
    }

    /**
     * @return list<ResourceDefinition>
     */
    public function all(): array
    {
        return array_values($this->resources);
    }
}
