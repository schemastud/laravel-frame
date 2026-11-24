<?php

namespace Schemastud\Frame\Tests;

use Schemastud\Frame\Data\ResourceSchemaPurpose;
use Schemastud\Frame\Data\ResourceSchemaQueryData;
use Schemastud\Frame\Registry\ContextBlockFacts;
use Schemastud\Frame\Registry\ContextManifest;
use Schemastud\Frame\Registry\FormMode;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceActionDefinition;
use Schemastud\Frame\Registry\ResourceActionResult;
use Schemastud\Frame\Registry\ResourceActionScope;
use Schemastud\Frame\Registry\ResourceCreateAffordance;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Registry\ResourceLayout;
use Schemastud\Frame\Tests\Fixtures\ContactResourceData;
use Schemastud\Frame\Tests\Fixtures\SampleModel;

class ResourceEnumContractTest extends TestCase
{
    public function test_backed_enums_keep_the_existing_wire_values(): void
    {
        $this->assertSame(
            ['resource', 'record'],
            array_map(static fn (ResourceActionScope $scope): string => $scope->value, ResourceActionScope::cases()),
        );
        $this->assertSame(
            ['toast', 'navigate'],
            array_map(static fn (ResourceActionResult $result): string => $result->value, ResourceActionResult::cases()),
        );
        $this->assertSame(
            ['single', 'subnav', 'master-detail'],
            array_map(static fn (ResourceLayout $layout): string => $layout->value, ResourceLayout::cases()),
        );
        $this->assertSame(
            ['frame', 'host'],
            array_map(static fn (ResourceCreateAffordance $affordance): string => $affordance->value, ResourceCreateAffordance::cases()),
        );
        $this->assertSame(['write', 'read'], array_map(
            static fn (ResourceSchemaPurpose $purpose): string => $purpose->value,
            ResourceSchemaPurpose::cases(),
        ));
    }

    public function test_action_and_query_data_still_serialize_their_backed_values(): void
    {
        $action = new ResourceActionDefinition(
            key: 'archive',
            label: 'Archive',
            scope: ResourceActionScope::Record,
            method: 'POST',
            url: '/api/contacts/{id}/archive',
            result: ResourceActionResult::Navigate,
        );

        $this->assertSame('record', $action->toArray()['scope']);
        $this->assertSame('navigate', $action->toArray()['result']);
        $this->assertSame('write', (new ResourceSchemaQueryData)->toArray()['purpose']);
        $this->assertSame('read', (new ResourceSchemaQueryData(ResourceSchemaPurpose::Read))->toArray()['purpose']);
    }

    public function test_definition_wither_copies_nullable_enum_fields_and_manifest_projects_strings(): void
    {
        $definition = new ResourceDefinition(
            key: 'contacts',
            model: SampleModel::class,
            data: ContactResourceData::class,
            creatable: true,
            query: null,
            editData: null,
            policy: null,
            nav: new NavMetadata(label: 'Contacts'),
            form: FormMode::Bare,
            layout: ResourceLayout::Subnav,
            createAffordance: ResourceCreateAffordance::Host,
            actions: [new ResourceActionDefinition(
                key: 'archive',
                label: 'Archive',
                scope: ResourceActionScope::Record,
                method: 'POST',
                url: '/api/contacts/{id}/archive',
                result: ResourceActionResult::Navigate,
            )],
        );

        $wire = $definition->toArray();
        $this->assertSame('bare', $wire['form']);
        $this->assertSame('subnav', $wire['layout']);
        $this->assertSame('host', $wire['createAffordance']);

        $copy = $definition->withOverrides();
        $this->assertSame(FormMode::Bare, $copy->form);
        $this->assertSame(ResourceLayout::Subnav, $copy->layout);
        $this->assertSame(ResourceCreateAffordance::Host, $copy->createAffordance);

        $changed = $definition->withOverrides(
            form: FormMode::Enriched,
            layout: ResourceLayout::MasterDetail,
            createAffordance: ResourceCreateAffordance::Frame,
        );
        $this->assertSame(FormMode::Enriched, $changed->form);
        $this->assertSame(ResourceLayout::MasterDetail, $changed->layout);
        $this->assertSame(ResourceCreateAffordance::Frame, $changed->createAffordance);

        $block = (new ContextManifest)->forResource(
            $definition->data,
            ContextBlockFacts::forDefinition($definition),
        );
        $this->assertSame('subnav', $block['layout']);
        $this->assertSame('host', $block['createAffordance']);
        $this->assertSame('record', $block['actions'][0]['scope']);
        $this->assertSame('navigate', $block['actions'][0]['result']);
    }
}
