<?php

namespace Schemastud\Frame\Strategies;

use ReflectionProperty;
use Schemastud\DataSchemas\Overlay\OverlayStack;
use Schemastud\DataSchemas\Overlay\SchemaOverlayApplier;
use Schemastud\DataSchemas\Strategies\SchemaStrategy;
use Schemastud\DataSchemas\Strategies\SchemaStrategyContext;
use Schemastud\Frame\Keywords;
use Schemastud\Frame\Registry\ContextManifest;
use Schemastud\Frame\Registry\WidgetContextProjector;

/**
 * Projects the `#[WidgetIn]` family (base + #[Column]/#[NotInList] sugar) on a
 * property to the `x-stud-widget-contexts` map keyword, embedded in the property's
 * schema (the #[Filterable] -> x-filter way, NOT a sidecar uiSchema). An ordinary
 * (not heavyweight) `edit` entry is also projected onto the form control,
 * `x-widget` / `x-widget-options` (ADR-0006); a property that also carries
 * `#[Widget]` is refused by the projector. The per-context
 * projection + closed-enum/arity validation is delegated to {@see WidgetContextProjector}
 * so the strategy and {@see ContextManifest} never diverge.
 *
 * DataOverlay convergence (DO-07 / ADR-0089 §6): the per-node projection now rides
 * the DataOverlay primitive — it is laid over the property schema as an `override`
 * overlay folded by {@see SchemaOverlayApplier}, rather than a hand-rolled array
 * assignment. The wire output is byte-for-byte identical (the projector still owns
 * the map; only SchemaOverlayApplier touches it, server-eager). Options are emitted
 * verbatim (`override`, never `merge`) — the options-cascade deep-merge is a CLIENT
 * concern (`resolveWidgetFor`) that stays entirely outside DataOverlay.
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

        $actions = [
            ['target' => "$['".Keywords::WidgetContexts."']", 'override' => $map],
        ];

        // An ordinary edit entry binds the form control: it is projected onto the node keyword the form reads, so the
        // entry and `x-widget` are one declaration and cannot disagree. A heavyweight one projects nothing.
        $control = WidgetContextProjector::ordinaryEditWidget($map);

        if ($control !== null) {
            $actions[] = ['target' => "$['".Keywords::Widget."']", 'override' => $control['name']];

            if ($control['options'] !== null) {
                $actions[] = ['target' => "$['".Keywords::WidgetOptions."']", 'override' => $control['options']];
            }
        }

        $overlay = new OverlayStack([[
            'overlay' => '1.0.0',
            'actions' => $actions,
        ]]);

        return (new SchemaOverlayApplier($schema, $overlay))->apply();
    }
}
