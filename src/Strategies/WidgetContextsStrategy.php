<?php

namespace Schemastud\Frame\Strategies;

use ReflectionProperty;
use Schemastud\DataSchemas\Strategies\SchemaStrategy;
use Schemastud\DataSchemas\Strategies\SchemaStrategyContext;
use Schemastud\Frame\Keywords;
use Schemastud\Frame\Registry\ContextManifest;
use Schemastud\Frame\Registry\WidgetContextProjector;

/**
 * Projects the `#[WidgetIn]` family (base + #[Column]/#[NotInList] sugar) on a
 * property to the `x-stud-widget-contexts` map keyword, embedded in the property's
 * schema (the #[Filterable] -> x-filter way, NOT a sidecar uiSchema). The per-context
 * projection + closed-enum/arity validation is delegated to {@see WidgetContextProjector}
 * so the strategy and {@see ContextManifest} never diverge.
 *
 * Self-registered into `config('data-schemas.strategies')`; a strict no-op for
 * properties without any WidgetIn-family attribute. Like every `x-*` keyword it is
 * stripped by `forLlmStrict`.
 */
class WidgetContextsStrategy implements SchemaStrategy
{
    public function apply(ReflectionProperty $property, array $schema, SchemaStrategyContext $context): array
    {
        $map = (new WidgetContextProjector)->forProperty($property);

        if (empty($map)) {
            return $schema;
        }

        $schema[Keywords::WidgetContexts] = $map;

        return $schema;
    }
}
