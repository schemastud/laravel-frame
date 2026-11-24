<?php

namespace Schemastud\Frame\Tests;

use Schemastud\Frame\Authorization\ResourceAuthorizer;
use Schemastud\Frame\Registry\ContextBlockFacts;
use Schemastud\Frame\Registry\ContextManifest;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceActionDefinition;
use Schemastud\Frame\Registry\ResourceCollectionAddress;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Registry\ResourceLayout;
use Schemastud\Frame\Tests\Fixtures\ContactResourceData;
use Schemastud\Frame\Tests\Fixtures\SampleModel;
use Spatie\LaravelData\Optional;
use TypeError;

/**
 * frame-foundation 94 (ruling 88): a context block's facts travel as one {@see ContextBlockFacts}, built from the
 * definition and the actor's authorizer by one factory, and `forResource()` takes the Data class and that object.
 */
class ContextBlockFactsTest extends TestCase
{
    /** 88 ruling 2: no window. The non-nullable parameter makes an old positional call fail loudly, not degrade. */
    public function test_an_old_positional_call_throws_a_type_error(): void
    {
        $this->expectException(TypeError::class);

        (new ContextManifest)->forResource(ContactResourceData::class, null, 'k');
    }

    public function test_an_old_positional_call_with_a_string_second_argument_throws_a_type_error(): void
    {
        $this->expectException(TypeError::class);

        (new ContextManifest)->forResource(ContactResourceData::class, 'subnav');
    }

    public function test_the_default_facts_emit_the_block_the_data_class_alone_gets(): void
    {
        $this->assertSame(
            (new ContextManifest)->forResource(ContactResourceData::class),
            (new ContextManifest)->forResource(ContactResourceData::class, new ContextBlockFacts),
        );
    }

    /** 88 ruling 8, 83's amend LOW 2: the constructor holds the non-empty `requires` invariant for any producer. */
    public function test_a_hand_built_empty_requires_list_never_reaches_the_wire(): void
    {
        foreach ([['x:read' => []], ['x:read' => [], 'x:write' => []], ['x:read' => ['']]] as $requires) {
            $facts = new ContextBlockFacts(requires: $requires);

            $this->assertSame([], $facts->requires, json_encode($requires));
            $this->assertArrayNotHasKey('requires', (new ContextManifest)->forResource(ContactResourceData::class, $facts), json_encode($requires));
        }

        $partial = new ContextBlockFacts(requires: ['x:read' => [], 'x:write' => ['terms', 'terms']]);
        $this->assertSame(['x:write' => ['terms']], $partial->requires);
        $this->assertSame(['x:write' => ['terms']], (new ContextManifest)->forResource(ContactResourceData::class, $partial)['requires']);
    }

    public function test_for_definition_reads_the_definitions_facts_and_without_an_authorizer_resolves_no_actor_axis(): void
    {
        $collections = [new ResourceCollectionAddress('mine', 'Mine')];
        $definition = $this->definition(
            actions: [$this->action('publish')],
            collections: $collections,
            requires: ['notes:read' => ['terms']],
        );

        $facts = ContextBlockFacts::forDefinition($definition);

        $this->assertSame('subnav', $facts->layout);
        $this->assertSame('notes', $facts->key);
        $this->assertSame($definition->resolvedCreateAffordance(), $facts->createAffordance);
        $this->assertSame($definition->resolvedSingularLabel(), $facts->singularLabel);
        $this->assertSame([], $facts->can, 'no authorizer: the "no actor axis" default');
        $this->assertSame($definition->actions, $facts->actions);
        $this->assertSame($collections, $facts->collections);
        $this->assertSame(['notes:read' => ['terms']], $facts->requires);
    }

    public function test_for_definition_turns_optional_requires_into_none(): void
    {
        $definition = $this->definition();

        $this->assertInstanceOf(Optional::class, $definition->requires);
        $this->assertSame([], ContextBlockFacts::forDefinition($definition)->requires);
        $this->assertArrayNotHasKey('requires', (new ContextManifest)->forResource($definition->data, ContextBlockFacts::forDefinition($definition)));
    }

    public function test_for_definition_composes_can_from_the_authorizer_with_actions_only_when_the_resource_declares_any(): void
    {
        $authorizer = app(ResourceAuthorizer::class);

        $plain = $this->definition();
        $this->assertSame($authorizer->capabilities($plain), ContextBlockFacts::forDefinition($plain, $authorizer)->can);
        $this->assertArrayNotHasKey('actions', ContextBlockFacts::forDefinition($plain, $authorizer)->can);

        $acting = $this->definition(actions: [$this->action('publish'), $this->action('archive')]);
        $this->assertSame(
            [...$authorizer->capabilities($acting), 'actions' => $authorizer->actionCapabilities($acting)],
            ContextBlockFacts::forDefinition($acting, $authorizer)->can,
        );
        $this->assertSame(['publish', 'archive'], array_keys(ContextBlockFacts::forDefinition($acting, $authorizer)->can['actions']));
    }

    /** Keys and their order are unchanged (EmittedWireKeys untouched); the contributor half is asked by `$facts->key` (ContextManifestTest). */
    public function test_a_block_built_through_for_definition_keeps_every_key_in_its_order(): void
    {
        $definition = $this->definition(actions: [$this->action('publish')], collections: [new ResourceCollectionAddress('mine', 'Mine')], requires: ['notes:read' => ['terms']]);
        $block = (new ContextManifest)->forResource($definition->data, ContextBlockFacts::forDefinition($definition));

        $this->assertSame(['byNode', 'inherits', 'known', 'layout', 'createAffordance', 'singularLabel', 'can', 'actions', 'requires', 'collections'], array_keys($block));
        $this->assertSame('subnav', $block['layout']);
        $this->assertSame([['stem' => 'mine', 'label' => 'Mine']], $block['collections']);
        $this->assertSame(['notes:read' => ['terms']], $block['requires']);
        $this->assertSame('publish', $block['actions'][0]['key']);
    }

    /**
     * @param  list<ResourceActionDefinition>  $actions
     * @param  list<ResourceCollectionAddress>  $collections
     * @param  array<string, list<string>>|Optional  $requires
     */
    private function definition(array $actions = [], array $collections = [], array|Optional $requires = new Optional): ResourceDefinition
    {
        return new ResourceDefinition(
            key: 'notes',
            model: SampleModel::class,
            data: ContactResourceData::class,
            creatable: true,
            query: null,
            editData: null,
            policy: null,
            nav: new NavMetadata(label: 'Notes'),
            layout: ResourceLayout::Subnav,
            actions: $actions,
            collections: $collections,
            requires: $requires,
        );
    }

    private function action(string $key): ResourceActionDefinition
    {
        return new ResourceActionDefinition(
            key: $key, label: ucfirst($key), scope: \Schemastud\Frame\Registry\ResourceActionScope::Record, method: 'POST', url: "/api/notes/{id}/{$key}",
        );
    }
}
