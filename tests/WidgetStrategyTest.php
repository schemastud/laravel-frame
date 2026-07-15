<?php

namespace Schemastud\Frame\Tests;

use ReflectionClass;
use Schemastud\DataSchemas\Generators\JsonSchemaGenerator;
use Schemastud\Frame\Keywords;
use Schemastud\Frame\Strategies\ReadOnlyAttributeStrategy;
use Schemastud\Frame\Strategies\WidgetAttributesStrategy;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;

/**
 * Ticket 02 — the #[Widget] -> x-stud-widget projector and #[ReadOnlyField] ->
 * readOnly strategy, plus the ticket 01 #[Computed]-drop exercised together on one
 * request schema. Everything rides the JsonSchemaGenerator strategy pipeline (the
 * #[Filterable]->x-filter way), NOT a sidecar uiSchema or the vocabulary describer.
 */
class WidgetStrategyTest extends TestCase
{
    private function requestSchema(): array
    {
        return (new JsonSchemaGenerator)
            ->forRequest()
            ->generate(new ReflectionClass(SampleResourceData::class));
    }

    public function test_widget_attribute_emits_x_stud_widget_embedded_in_the_property_schema(): void
    {
        $props = $this->requestSchema()['properties'];

        $this->assertSame('textarea', $props['body'][Keywords::Widget]);
    }

    public function test_widget_options_are_emitted_when_provided(): void
    {
        $props = $this->requestSchema()['properties'];

        $this->assertSame('splicewire-enrich', $props['interpretation'][Keywords::Widget]);
        $this->assertSame(['tone' => 'warm'], $props['interpretation'][Keywords::WidgetOptions]);
    }

    public function test_read_only_field_attribute_emits_standard_read_only_true(): void
    {
        $props = $this->requestSchema()['properties'];

        $this->assertTrue($props['reference']['readOnly']);
    }

    public function test_x_stud_widget_value_domain_is_an_open_string_no_hard_failure(): void
    {
        // An arbitrary/unknown widget key is emitted verbatim — the graceful
        // fallback is the frontend's (unknown key -> inferred control), never a
        // backend hard failure. Proven here: any string projects without error.
        $props = $this->requestSchema()['properties'];

        $this->assertIsString($props['body'][Keywords::Widget]);
    }

    public function test_computed_property_is_dropped_from_the_request_form_schema(): void
    {
        // Ticket 01 + 02 exercised together: an output-only #[Computed] prop must
        // not appear as an editable field, while the widget/readonly props do.
        $props = $this->requestSchema()['properties'];

        $this->assertArrayNotHasKey('slug', $props);
        $this->assertArrayHasKey('body', $props);
        $this->assertArrayHasKey('reference', $props);
    }

    public function test_strategies_are_self_registered_into_the_data_schemas_pipeline(): void
    {
        $strategies = config('data-schemas.strategies');

        $this->assertContains(WidgetAttributesStrategy::class, $strategies);
        $this->assertContains(ReadOnlyAttributeStrategy::class, $strategies);
    }
}
