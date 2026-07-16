<?php

namespace Schemastud\Frame\Strategies;

use ReflectionProperty;
use Schemastud\DataSchemas\Strategies\SchemaStrategy;
use Schemastud\DataSchemas\Strategies\SchemaStrategyContext;
use Schemastud\Frame\Attributes\ResourceRef;
use Schemastud\Frame\Keywords;

/**
 * Projects `#[ResourceRef('…')]` on an edit-DTO property to the
 * `x-stud-resource-ref` JSON-Schema vendor keyword, embedded in the property's
 * schema — mirrors WidgetAttributesStrategy. Self-registered into
 * `config('data-schemas.strategies')` by the frame service provider; a strict
 * no-op for properties without the attribute, so adding it never changes other
 * output. Like every `x-*` keyword it is stripped by `forLlmStrict`.
 */
class ResourceRefStrategy implements SchemaStrategy
{
    public function apply(ReflectionProperty $property, array $schema, SchemaStrategyContext $context): array
    {
        $attrs = $property->getAttributes(ResourceRef::class);

        if (empty($attrs)) {
            return $schema;
        }

        $ref = $attrs[0]->newInstance();

        $config = [
            'resource' => $ref->resource,
            'value' => $ref->value,
            'label' => $ref->label,
            'multiple' => $ref->multiple,
        ];

        if ($ref->scope !== null) {
            $config['scope'] = $ref->scope;
        }

        $schema[Keywords::ResourceRef] = $config;

        return $schema;
    }
}
