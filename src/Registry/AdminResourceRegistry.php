<?php

namespace Schemastud\Frame\Registry;

use InvalidArgumentException;
use ReflectionClass;
use Schemastud\Frame\Attributes\AdminResource;
use Symfony\Component\Finder\Finder;

/**
 * The runtime map of resource key -> editor wiring (frame's manifest source). Sits
 * one level above a filter-schema controller: that resolves a key to a *schema*,
 * this resolves a key to the *whole editor wiring* (AdminResourceDefinition).
 *
 * Discovery is the #[AdminResource] attribute (single source of truth): the boot
 * scan reflects the configured resource classes and any classes found under the
 * configured discover paths, plus a ->register() escape hatch for attribute-less
 * resources. Mirrors data-filters' ResourceRegistry + the CompositionIntakeRegistry
 * singleton-scan pattern.
 */
class AdminResourceRegistry
{
    /** @var array<string, AdminResourceDefinition> */
    private array $resources = [];

    /**
     * Reflect an #[AdminResource]-annotated Data class and register its definition.
     *
     * @param  class-string  $dataClass
     */
    public function registerClass(string $dataClass): void
    {
        if (! class_exists($dataClass)) {
            throw new InvalidArgumentException("Admin resource class [{$dataClass}] does not exist.");
        }

        $attribute = $this->readAttribute(new ReflectionClass($dataClass));

        if ($attribute === null) {
            throw new InvalidArgumentException(
                "Class [{$dataClass}] is not annotated with #[AdminResource]; use register() for attribute-less resources."
            );
        }

        $this->register(AdminResourceDefinition::fromAttribute($dataClass, $attribute));
    }

    /**
     * The ->register() escape hatch: add an attribute-less resource imperatively
     * (numero's layers/layer_nodes, or any resource authored without the attribute).
     */
    public function register(AdminResourceDefinition $definition): void
    {
        $this->resources[$definition->key] = $definition;
    }

    /**
     * Scan configured class-strings and discover-paths for #[AdminResource] classes.
     * Idempotent — re-scanning overwrites by key, never duplicates.
     *
     * @param  array<int, class-string>  $classes  explicit resource class list
     * @param  array<int, string>  $paths  filesystem paths to scan for annotated classes
     */
    public function discover(array $classes = [], array $paths = []): void
    {
        foreach ($classes as $class) {
            $this->registerClass($class);
        }

        foreach ($this->scanPaths($paths) as $class) {
            $this->registerClass($class);
        }
    }

    public function has(string $key): bool
    {
        return isset($this->resources[$key]);
    }

    public function get(string $key): AdminResourceDefinition
    {
        return $this->resources[$key] ?? throw new InvalidArgumentException(
            "No frame resource registered for key [{$key}]."
        );
    }

    /**
     * The served manifest: flat sibling entries, insertion order.
     *
     * @return list<AdminResourceDefinition>
     */
    public function all(): array
    {
        return array_values($this->resources);
    }

    private function readAttribute(ReflectionClass $class): ?AdminResource
    {
        $attrs = $class->getAttributes(AdminResource::class);

        return empty($attrs) ? null : $attrs[0]->newInstance();
    }

    /**
     * Find #[AdminResource]-annotated class-strings under the given paths. Only
     * classes carrying the attribute are returned (others are ignored, not errors),
     * so a discover path may point at a whole Data directory.
     *
     * @param  array<int, string>  $paths
     * @return list<class-string>
     */
    private function scanPaths(array $paths): array
    {
        $existing = array_filter($paths, 'file_exists');

        if (empty($existing)) {
            return [];
        }

        $found = [];

        $finder = (new Finder)->files()->name('*.php')->in($existing);

        foreach ($finder as $file) {
            $class = $this->classNameFromFile($file->getRealPath());

            if ($class === null || ! class_exists($class)) {
                continue;
            }

            if ($this->readAttribute(new ReflectionClass($class)) !== null) {
                $found[] = $class;
            }
        }

        return $found;
    }

    private function classNameFromFile(string $path): ?string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $namespace = preg_match('/namespace\s+([^;]+);/', $contents, $ns) ? trim($ns[1]) : '';

        if (! preg_match('/\bclass\s+(\w+)/', $contents, $cls)) {
            return null;
        }

        return $namespace === '' ? $cls[1] : $namespace.'\\'.$cls[1];
    }
}
