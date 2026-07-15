<?php

namespace Schemastud\Frame\Strategies;

use ReflectionProperty;
use Schemastud\DataSchemas\Strategies\SchemaStrategy;
use Schemastud\DataSchemas\Strategies\SchemaStrategyContext;
use Schemastud\Frame\Attributes\ReadOnlyField;

/**
 * Maps `#[ReadOnlyField]` to the standard JSON-Schema `readOnly: true` keyword
 * (present-but-disabled in the form). Self-registered into
 * `config('data-schemas.strategies')`; a strict no-op without the attribute.
 *
 * Uses the standard `readOnly` keyword (not an `x-*` extension), matching the
 * generator's own #[Lazy] projection — so it is stripped by `forLlmStrict` for the
 * same reason `readOnly` always is (LLM providers reject it).
 */
class ReadOnlyAttributeStrategy implements SchemaStrategy
{
    public function apply(ReflectionProperty $property, array $schema, SchemaStrategyContext $context): array
    {
        if (! empty($property->getAttributes(ReadOnlyField::class))) {
            $schema['readOnly'] = true;
        }

        return $schema;
    }
}
