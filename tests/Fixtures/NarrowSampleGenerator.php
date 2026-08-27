<?php

namespace Schemastud\Frame\Tests\Fixtures;

use ReflectionClass;
use Schemastud\DataSchemas\Generators\Generator;

/**
 * A NARROW generator: accepts exactly one class, refuses everything else.
 *
 * Stands in for `schemastud/laravel-blockdoc`'s `BlockJsonSchemaGenerator`, whose `canGenerate()` is
 * `isSubclassOf(Block::class)` and which bridges a block's `#[NodeType]`/`#[NodeAttr]` declarations
 * into the schema. `~/Herd/thingsontv` configures `[BlockJsonSchemaGenerator, JsonSchemaGenerator]`
 * and installs frame — and because a `Block` IS a `Data`, BOTH members accept a Block-backed
 * resource. Only dispatch order decides which one runs, so a consumer that hand-builds the plain
 * generator silently produces the unbridged document.
 *
 * Marks its output so a test can tell WHICH member produced a schema rather than inferring it.
 */
class NarrowSampleGenerator implements Generator
{
    public string $mode = 'collapsed';

    public function canGenerate(ReflectionClass $class): bool
    {
        return $class->getName() === SampleResourceData::class;
    }

    public function generate(ReflectionClass $class): array
    {
        return [
            'x-generated-by' => static::class,
            'x-mode' => $this->mode,
            'type' => 'object',
            'title' => $class->getShortName(),
        ];
    }

    public function forRequest(): static
    {
        return $this->withMode('request');
    }

    public function forResponse(): static
    {
        return $this->withMode('response');
    }

    public function forLlmStrict(): static
    {
        return $this->withMode('llm_strict');
    }

    public function schemaMode(string $mode): static
    {
        return $this->withMode($mode);
    }

    /** Every mode call CLONES, as JsonSchemaGenerator does — never mutates in place. */
    protected function withMode(string $mode): static
    {
        $clone = clone $this;
        $clone->mode = $mode;

        return $clone;
    }
}
