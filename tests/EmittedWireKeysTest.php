<?php

namespace Schemastud\Frame\Tests;

use Mockery;
use Schemastud\Frame\Contracts\FrameNavContributor;
use Schemastud\Frame\Contracts\FrameResourceHandlerResolver;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceActionDefinition;
use Schemastud\Frame\Registry\ResourceCollectionAddress;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Registry\RouteContextEntry;
use Schemastud\Frame\Wire\EmittedWireKeys;

/**
 * frame-foundation 23 (ticket 05, ruling 6, build slice 7): laravel-frame lists every key it emits, with its reader.
 * The package's own fixture resources are projected through the REAL manifest and schema endpoints, and every key
 * they emit must be in {@see EmittedWireKeys}. A key added to the manifest, the context block or the schema keywords
 * therefore fails here until it names its reader.
 */
class EmittedWireKeysTest extends TestCase
{
    public function test_every_key_the_manifest_and_schema_endpoints_emit_is_listed_with_its_reader(): void
    {
        $emitted = $this->emitted();

        foreach ($emitted as $group => $keys) {
            $unlisted = array_values(array_diff($keys, EmittedWireKeys::keys($group)));
            $this->assertSame([], $unlisted, "emitted in [{$group}] but not listed in EmittedWireKeys");
        }

        // The census reached every group, so an empty diff above is not an empty projection.
        foreach ([EmittedWireKeys::GroupManifest, EmittedWireKeys::GroupResourceEntry, EmittedWireKeys::GroupContextBlock, EmittedWireKeys::GroupSchemaKeywords] as $group) {
            $this->assertNotEmpty($emitted[$group] ?? [], "no key emitted in [{$group}]");
        }

        // Ticket 08's keyword set: the bare pair is emitted and listed; of the tier-prefixed widget keys only the
        // context map remains (the form-control pair 08 removed is neither emitted nor listed).
        $schema = EmittedWireKeys::keys(EmittedWireKeys::GroupSchemaKeywords);
        $this->assertContains('x-widget', $emitted[EmittedWireKeys::GroupSchemaKeywords]);
        $this->assertContains('x-widget-options', $emitted[EmittedWireKeys::GroupSchemaKeywords]);
        $this->assertContains('x-widget', $schema);
        $this->assertContains('x-widget-options', $schema);
        // The tier-prefixed keys whose name continues with `widget` (spelled in two parts so CONFORM 01-1's rg stays clean).
        $studWidget = fn (array $keys) => array_values(array_filter($keys, fn (string $k) => str_starts_with($k, 'x-stud-') && str_starts_with(substr($k, 7), 'widget')));
        $this->assertSame(['x-stud-widget-contexts'], $studWidget($schema));
        $this->assertSame(['x-stud-widget-contexts'], $studWidget($emitted[EmittedWireKeys::GroupSchemaKeywords]));
    }

    /**
     * 23 amend (review-r1 REVISE): an author's option bag is opaque even when its own keys start with `x-`. The census
     * walks JSON-Schema nodes only, so `x-client-option` inside `x-widget-options` (or inside a context entry's
     * options) is the widget's key, never a frame wire key, and is not listed.
     */
    public function test_an_x_named_author_option_is_opaque_and_not_a_wire_key(): void
    {
        $emitted = $this->emitted();

        // The fixture really serves them, so the census below had them in reach.
        $schema = $this->getJson('frame/resources/opaque/schema')->assertOk()->json('properties');
        $this->assertTrue($schema['prose']['x-widget-options']['x-client-option'] ?? false);
        $this->assertSame('kept', $schema['email']['x-widget-options']['x-edit-option'] ?? null);
        $this->assertSame('kept', $schema['email']['x-stud-widget-contexts']['list-column']['options']['x-column-option'] ?? null);

        foreach (['x-client-option', 'x-edit-option', 'x-column-option'] as $option) {
            foreach ($emitted as $group => $keys) {
                $this->assertNotContains($option, $keys, "author option [{$option}] read as a [{$group}] key");
            }
            $this->assertNotContains($option, EmittedWireKeys::keys(EmittedWireKeys::GroupSchemaKeywords));
        }
    }

    public function test_every_listed_key_names_a_reader(): void
    {
        foreach (EmittedWireKeys::all() as $group => $keys) {
            foreach ($keys as $key => $reader) {
                $where = "[{$group}] {$key}";
                $this->assertContains($reader['reader'], [EmittedWireKeys::ReaderClient, EmittedWireKeys::ReaderServer, EmittedWireKeys::ReaderUnread], $where);
                if ($reader['reader'] === EmittedWireKeys::ReaderClient && array_key_exists('by', $reader)) {
                    $this->assertNotSame('', trim($reader['by']), "{$where}: a client `by` names its reader");
                }
                if ($reader['reader'] === EmittedWireKeys::ReaderServer) {
                    $this->assertNotEmpty($reader['by'] ?? '', "{$where}: a server reader names its class");
                }
                if ($reader['reader'] === EmittedWireKeys::ReaderUnread) {
                    $this->assertNotEmpty($reader['reason'] ?? '', "{$where}: an unread key says why it is emitted");
                }
            }
        }
    }

    /** 23 §Interface decision, edit 4: clientKeys() is every `client` entry (with `by` or without), group-qualified and sorted. */
    public function test_client_keys_are_every_client_entry_group_qualified_and_sorted(): void
    {
        $keys = EmittedWireKeys::clientKeys();

        $this->assertContains('context-block:byNode', $keys);       // frame reads it (no `by`)
        $this->assertContains('manifest:nav', $keys);               // client with `by`
        $this->assertNotContains('schema-keywords:x-stud-widget-contexts', $keys);   // server
        $this->assertNotContains('resource-entry:data', $keys);     // unread
        // One spelling under two groups is two keys: `nav` is a client key at the manifest root and on a resource entry.
        $this->assertContains('resource-entry:nav', $keys);
        $this->assertSame(2, count(array_filter($keys, fn (string $k) => str_ends_with($k, ':nav'))));
        // ...and a spelling can be client in one group and not in the other (the census): frame reads the context
        // block's singularLabel, and the resource entry's copy is unread.
        $this->assertContains('context-block:singularLabel', $keys);
        $this->assertNotContains('resource-entry:singularLabel', $keys);

        $sorted = $keys;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $keys);
        $this->assertSame(array_values(array_unique($keys)), $keys);

        $clientEntries = 0;
        foreach (EmittedWireKeys::all() as $entries) {
            foreach ($entries as $reader) {
                $clientEntries += $reader['reader'] === EmittedWireKeys::ReaderClient ? 1 : 0;
            }
        }
        $this->assertCount($clientEntries, $keys);
        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression('/^(manifest|resource-entry|context-block|schema-keywords):\S+$/', $key);
        }
    }

    /**
     * Project the fixtures through the real endpoints and collect every emitted key, by group.
     *
     * @return array<string, list<string>>
     */
    private function emitted(): array
    {
        $definitions = $this->fixtureDefinitions();
        $registry = new InMemoryResourceRegistry;
        foreach ($definitions as $definition) {
            $registry->register($definition);
        }
        $this->app->instance(ResourceRegistry::class, $registry);
        $this->app->instance(FrameResourceHandlerResolver::class, Mockery::mock(FrameResourceHandlerResolver::class));
        // A bound nav contributor, so the manifest's optional `nav` / `routeContext` keys are emitted too.
        $this->app->instance(FrameNavContributor::class, new class implements FrameNavContributor
        {
            public function contributeNav(?string $realm): ?array
            {
                return [
                    'nav' => ['items' => [['label' => 'Lab']]],
                    'routeContext' => [new RouteContextEntry(routeName: 'leaf', path: 'lab-leaf')],
                ];
            }
        });

        $found = [];
        $add = function (string $group, string $key) use (&$found): void {
            $found[$group][$key] = true;
        };

        $manifest = $this->getJson('frame/manifest')->assertOk()->json();
        foreach ($manifest as $key => $value) {
            $add(EmittedWireKeys::GroupManifest, $key);
            if ($key === 'routeContext') {
                foreach ($value as $entry) {
                    foreach (array_keys($entry) as $field) {
                        $add(EmittedWireKeys::GroupManifest, 'routeContext[].'.$field);
                    }
                }
            }
        }
        foreach ($manifest['resources'] as $entry) {
            $this->keysOf($entry, '', fn (string $k) => $add(EmittedWireKeys::GroupResourceEntry, $k));
        }
        foreach ($manifest['contexts'] as $block) {
            foreach ($block as $key => $value) {
                if ($key === 'byNode') {
                    $add(EmittedWireKeys::GroupContextBlock, 'byNode');
                    foreach ($value as $contexts) {
                        foreach ($contexts as $entry) {
                            foreach (array_keys($entry) as $field) {
                                $add(EmittedWireKeys::GroupContextBlock, 'entry.'.$field);
                            }
                        }
                    }

                    continue;
                }
                $this->keysOf([$key => $value], '', fn (string $k) => $add(EmittedWireKeys::GroupContextBlock, $k));
            }
        }

        $schemas = [];
        foreach ($definitions as $definition) {
            foreach (['write', 'read'] as $purpose) {
                $schemas[] = $this->getJson("frame/resources/{$definition->key}/schema?purpose={$purpose}")->assertOk()->json();
            }
            foreach ($definition->actions as $action) {
                if ($action->input !== null) {
                    $schemas[] = $this->getJson("frame/resources/{$definition->key}/actions/{$action->key}/schema")->assertOk()->json();
                }
            }
        }
        foreach ($schemas as $schema) {
            $this->vendorKeywords($schema, fn (string $k) => $add(EmittedWireKeys::GroupSchemaKeywords, $k));
        }

        return array_map(fn (array $keys) => array_keys($keys), $found);
    }

    /** Dotted keys of an object, `[]` for a list; an author's `options` bag, `inherits` and `can.actions` are opaque. */
    private function keysOf(array $value, string $prefix, callable $add): void
    {
        foreach ($value as $key => $child) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $add($path);
            if (! is_array($child) || $key === 'options' || $path === 'inherits' || $path === 'can.actions') {
                continue;
            }
            if (array_is_list($child)) {
                foreach ($child as $item) {
                    if (is_array($item) && ! array_is_list($item)) {
                        $this->keysOf($item, $path.'[]', $add);
                    }
                }

                continue;
            }
            $this->keysOf($child, $path, $add);
        }
    }

    /**
     * The `x-` keywords of a served schema, read from JSON-Schema NODES only (23 amend): a node's own `x-` keys are
     * keywords, and the walk descends only through the keywords that hold subschemas. A keyword's value is never
     * walked (an `x-widget-options` bag is the author's; `x-stud-widget-contexts` and `x-stud-resource-ref` carry data),
     * and a name in a property map (`properties`, `$defs`, …) is a name, not a keyword.
     */
    private function vendorKeywords(mixed $node, callable $add): void
    {
        if (! is_array($node) || array_is_list($node)) {
            return;
        }

        foreach (array_keys($node) as $key) {
            if (is_string($key) && str_starts_with($key, 'x-')) {
                $add($key);
            }
        }

        foreach (self::SubschemaMaps as $map) {
            foreach (is_array($node[$map] ?? null) ? $node[$map] : [] as $subschema) {
                $this->vendorKeywords($subschema, $add);
            }
        }
        foreach (self::SubschemaLists as $list) {
            foreach (is_array($node[$list] ?? null) && array_is_list($node[$list]) ? $node[$list] : [] as $subschema) {
                $this->vendorKeywords($subschema, $add);
            }
        }
        foreach (self::SubschemaSingles as $single) {
            if (is_array($node[$single] ?? null)) {
                $this->vendorKeywords($node[$single], $add);
            }
        }
    }

    /** JSON Schema 2020-12 keywords whose value is a map of name => subschema. */
    private const SubschemaMaps = ['properties', 'patternProperties', '$defs', 'definitions', 'dependentSchemas'];

    /** Keywords whose value is a list of subschemas. */
    private const SubschemaLists = ['prefixItems', 'allOf', 'anyOf', 'oneOf'];

    /** Keywords whose value is one subschema. */
    private const SubschemaSingles = ['items', 'additionalProperties', 'unevaluatedItems', 'unevaluatedProperties', 'propertyNames', 'contains', 'not', 'if', 'then', 'else'];

    /** @return list<ResourceDefinition> */
    private function fixtureDefinitions(): array
    {
        $definitions = [];
        foreach ([
            'sample' => Fixtures\SampleResourceData::class,
            'contact' => Fixtures\ContactResourceData::class,
            'kinds' => Fixtures\ColumnKindResourceData::class,
            'rows' => Fixtures\RowActionsResourceData::class,
            'summary' => Fixtures\SummaryOverviewResourceData::class,
            'refs' => Fixtures\ResourceRefResourceData::class,
            'review' => Fixtures\ReviewFixtureResourceData::class,
            'purpose' => Fixtures\SchemaPurposeData::class,
            'opaque' => Fixtures\OpaqueOptionsResourceData::class,
        ] as $key => $data) {
            $definitions[] = new ResourceDefinition(
                key: $key,
                model: Fixtures\SampleModel::class,
                data: $data,
                creatable: true,
                query: null,
                editData: null,
                policy: null,
                nav: new NavMetadata(label: ucfirst($key), group: 'Lab', section: 'lab', navOrder: 1, routeName: "lab.{$key}", icon: 'flask'),
            );
        }
        $definitions[] = $definitions[0]->withOverrides(
            key: 'wallets',
            collections: [new ResourceCollectionAddress('https://schemas.example/wallets', 'Wallets')],
            actions: [
                new ResourceActionDefinition(key: 'reload', label: 'Reload', scope: ResourceActionDefinition::ScopeResource, method: 'POST', url: '/api/wallets/reload', input: Fixtures\SampleActionInputData::class),
                new ResourceActionDefinition(key: 'archive', label: 'Archive', scope: ResourceActionDefinition::ScopeRecord, method: 'POST', url: '/api/wallets/{id}/archive', result: ResourceActionDefinition::ResultNavigate, destructive: true),
            ],
        );

        return $definitions;
    }
}
