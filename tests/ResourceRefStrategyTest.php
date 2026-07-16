<?php

namespace Schemastud\Frame\Tests;

use ReflectionClass;
use Schemastud\DataSchemas\Generators\JsonSchemaGenerator;
use Schemastud\Frame\Keywords;
use Schemastud\Frame\Strategies\ResourceRefStrategy;
use Schemastud\Frame\Tests\Fixtures\ResourceRefResourceData;

/**
 * FC-04 — the #[ResourceRef] -> x-stud-resource-ref projector. Rides the same
 * JsonSchemaGenerator strategy pipeline as #[Widget] (the #[Filterable]->x-filter
 * way), NOT a sidecar uiSchema.
 */
class ResourceRefStrategyTest extends TestCase
{
    private function requestSchema(): array
    {
        return (new JsonSchemaGenerator)
            ->forRequest()
            ->generate(new ReflectionClass(ResourceRefResourceData::class));
    }

    public function test_single_resource_ref_emits_the_config_bag_with_multiple_false_and_no_scope(): void
    {
        $props = $this->requestSchema()['properties'];

        $this->assertSame(
            [
                'resource' => 'plans',
                'value' => 'slug',
                'label' => 'name',
                'multiple' => false,
            ],
            $props['plan'][Keywords::ResourceRef],
        );

        // scope is omitted entirely when null.
        $this->assertArrayNotHasKey('scope', $props['plan'][Keywords::ResourceRef]);
    }

    public function test_multiple_scoped_resource_ref_emits_multiple_true_and_scope(): void
    {
        $props = $this->requestSchema()['properties'];

        $this->assertSame(
            [
                'resource' => 'starter-packs',
                'value' => 'slug',
                'label' => 'name',
                'multiple' => true,
                'scope' => ['status' => 'active'],
            ],
            $props['starterPacks'][Keywords::ResourceRef],
        );
    }

    public function test_multiple_is_always_a_bool_on_the_wire(): void
    {
        $props = $this->requestSchema()['properties'];

        $this->assertIsBool($props['plan'][Keywords::ResourceRef]['multiple']);
        $this->assertIsBool($props['starterPacks'][Keywords::ResourceRef]['multiple']);
    }

    public function test_properties_without_the_attribute_are_a_strict_no_op(): void
    {
        $props = $this->requestSchema()['properties'];

        $this->assertArrayNotHasKey(Keywords::ResourceRef, $props['title']);
    }

    public function test_strategy_is_self_registered_into_the_data_schemas_pipeline(): void
    {
        $strategies = config('data-schemas.strategies');

        $this->assertContains(ResourceRefStrategy::class, $strategies);
    }

    public function test_resource_ref_is_owned(): void
    {
        $this->assertContains(Keywords::ResourceRef, Keywords::owned());
    }
}
