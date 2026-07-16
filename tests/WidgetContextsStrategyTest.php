<?php

namespace Schemastud\Frame\Tests;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;
use Schemastud\DataSchemas\Generators\JsonSchemaGenerator;
use Schemastud\DataSchemas\Strategies\SchemaStrategyContext;
use Schemastud\Frame\Attributes\WidgetIn;
use Schemastud\Frame\Keywords;
use Schemastud\Frame\Strategies\WidgetContextsStrategy;
use Schemastud\Frame\Tests\Fixtures\ContactResourceData;
use Spatie\LaravelData\Data;

/**
 * The #[WidgetIn] family -> x-stud-widget-contexts projector. Rides the
 * JsonSchemaGenerator strategy pipeline embedded in the property schema (the
 * #[Filterable] -> x-filter way), and delegates projection/validation to the shared
 * WidgetContextProjector.
 */
class WidgetContextsStrategyTest extends TestCase
{
    private function requestSchema(): array
    {
        return (new JsonSchemaGenerator)
            ->forRequest()
            ->generate(new ReflectionClass(ContactResourceData::class));
    }

    public function test_strategy_is_self_registered_into_the_data_schemas_pipeline(): void
    {
        $this->assertContains(WidgetContextsStrategy::class, config('data-schemas.strategies'));
    }

    public function test_multi_context_property_emits_a_per_context_map(): void
    {
        $email = $this->requestSchema()['properties']['email'][Keywords::WidgetContexts];

        $this->assertSame([
            'edit' => [
                'participates' => true,
                'widget' => 'email-input',
                'options' => ['autocomplete' => 'email'],
            ],
            'list-column' => [
                'participates' => true,
                'widget' => 'email-cell',
                'options' => ['filterable' => true],
                'sort' => 1,
                'label' => 'Email',
            ],
            'row-cell' => [
                'participates' => true,
            ],
        ], $email);
    }

    public function test_options_are_emitted_verbatim_un_merged(): void
    {
        $email = $this->requestSchema()['properties']['email'][Keywords::WidgetContexts];

        // Authoring bag passes through untouched — the server does NOT merge defaults.
        $this->assertSame(['autocomplete' => 'email'], $email['edit']['options']);
        $this->assertSame(['filterable' => true], $email['list-column']['options']);
    }

    public function test_not_in_list_marks_list_column_as_not_participating(): void
    {
        $secret = $this->requestSchema()['properties']['secret'][Keywords::WidgetContexts];

        $this->assertFalse($secret['list-column']['participates']);
        $this->assertArrayNotHasKey('widget', $secret['list-column']);
    }

    public function test_heavyweight_edit_widget_flag_is_emitted(): void
    {
        $graph = $this->requestSchema()['properties']['graph'][Keywords::WidgetContexts];

        $this->assertTrue($graph['edit']['heavyweight']);
        $this->assertSame('circuit-graph', $graph['edit']['widget']);
    }

    public function test_bind_true_participates_without_an_explicit_widget(): void
    {
        // row-cell used bind default (true) -> participates, widget-default (no widget key).
        $email = $this->requestSchema()['properties']['email'][Keywords::WidgetContexts];

        $this->assertTrue($email['row-cell']['participates']);
        $this->assertArrayNotHasKey('widget', $email['row-cell']);
    }

    public function test_strict_no_op_when_no_widget_in_attribute_present(): void
    {
        $property = new ReflectionProperty(
            new class extends Data
            {
                public string $plain = '';
            },
            'plain',
        );

        $schema = ['type' => 'string'];
        $result = (new WidgetContextsStrategy)->apply($property, $schema, new SchemaStrategyContext([], 'request'));

        $this->assertSame($schema, $result);
        $this->assertArrayNotHasKey(Keywords::WidgetContexts, $result);
    }

    public function test_unknown_context_throws(): void
    {
        $subject = new class
        {
            #[WidgetIn('bogus')]
            public string $field = '';
        };

        $this->expectException(InvalidArgumentException::class);

        (new WidgetContextsStrategy)->apply(
            new ReflectionProperty($subject, 'field'),
            [],
            new SchemaStrategyContext([], 'request'),
        );
    }

    public function test_list_item_on_a_property_throws(): void
    {
        $subject = new class
        {
            #[WidgetIn('list-item')]
            public string $field = '';
        };

        $this->expectException(InvalidArgumentException::class);

        (new WidgetContextsStrategy)->apply(
            new ReflectionProperty($subject, 'field'),
            [],
            new SchemaStrategyContext([], 'request'),
        );
    }
}
