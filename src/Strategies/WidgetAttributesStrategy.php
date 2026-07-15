<?php

namespace Schemastud\Frame\Strategies;

use ReflectionProperty;
use Schemastud\DataSchemas\Strategies\SchemaStrategy;
use Schemastud\DataSchemas\Strategies\SchemaStrategyContext;
use Schemastud\Frame\Attributes\Widget;
use Schemastud\Frame\Keywords;

/**
 * Projects `#[Widget('…')]` on an edit-DTO property to the `x-stud-widget`
 * JSON-Schema vendor keyword (and optional `x-stud-widget-options`), embedded in
 * the property's schema — mirrors FilterableAttributesStrategy. Self-registered
 * into `config('data-schemas.strategies')` by the frame service provider; a strict
 * no-op for properties without the attribute, so adding it never changes other
 * output. Like every `x-*` keyword it is stripped by `forLlmStrict`.
 */
class WidgetAttributesStrategy implements SchemaStrategy
{
    public function apply(ReflectionProperty $property, array $schema, SchemaStrategyContext $context): array
    {
        $attrs = $property->getAttributes(Widget::class);

        if (empty($attrs)) {
            return $schema;
        }

        $widget = $attrs[0]->newInstance();
        $schema[Keywords::Widget] = $widget->name;

        if ($widget->options !== null) {
            $schema[Keywords::WidgetOptions] = $widget->options;
        }

        return $schema;
    }
}
