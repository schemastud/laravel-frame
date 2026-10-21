<?php

namespace Schemastud\Frame\Tests;

use Illuminate\Http\Request;
use Mockery;
use Schemastud\DataSchemas\Generators\JsonSchemaGenerator;
use Schemastud\Frame\Contracts\FrameResourceHandlerResolver;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Contracts\ResourceSchemaResolver;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\ContactResourceData;
use Schemastud\Frame\Tests\Fixtures\NarrowSampleGenerator;
use Schemastud\Frame\Tests\Fixtures\SampleActionInputData;
use Schemastud\Frame\Tests\Fixtures\SampleCreateResultData;
use Schemastud\Frame\Tests\Fixtures\SampleModel;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;

/**
 * `GET {prefix}/resources/{resource}/schema` — the edit form's contract.
 *
 * The endpoint projects through the HOST'S CONFIGURED generator chain, `app(Generator::class)`, not
 * a hand-built `new JsonSchemaGenerator(config('data-schemas', []))`. That older construction read
 * config correctly but could not DISPATCH: `data-schemas.generators` is a list, and the rule "the
 * first member whose `canGenerate()` accepts this class" lives only in `ChainedGenerator`.
 *
 * `~/Herd/thingsontv` is the estate's only multi-generator host — `[BlockJsonSchemaGenerator,
 * JsonSchemaGenerator]` — and it installs frame. Because a `Block` IS a `Data`, both members accept
 * a Block-backed resource and only dispatch order picks the right one, so the old code ran the plain
 * generator and dropped the block's `#[NodeType]`/`#[NodeAttr]` bridging: an edit form missing the
 * attributes it exists to edit, behind an HTTP 200.
 *
 * These tests take that shape with a narrow fixture generator configured FIRST.
 */
class ResourceSchemaEndpointTest extends TestCase
{
    public function test_read_purpose_uses_the_declared_read_shape_and_response_mode(): void
    {
        $definition = $this->definition('purpose', Fixtures\SchemaPurposeData::class)
            ->withOverrides(editData: SampleActionInputData::class);
        $this->app->instance(ResourceRegistry::class, (new InMemoryResourceRegistry)->register($definition));

        $write = $this->getJson('frame/resources/purpose/schema')->assertOk()->json();
        $this->assertSame($write, $this->getJson('frame/resources/purpose/schema?purpose=write')->assertOk()->json());
        $this->assertArrayNotHasKey('approvalState', $write['properties']);
        $read = $this->getJson('frame/resources/purpose/schema?purpose=read')->assertOk()->json();
        $this->assertArrayHasKey('approvalState', $read['properties']);
        $this->assertArrayHasKey('receipt', $read['properties']);
        $this->assertArrayHasKey('output_title', $read['properties']);
        $this->assertArrayNotHasKey('input_title', $read['properties']);
        $this->assertArrayNotHasKey('privateValue', $read['properties']);
        $this->assertContains('output_title', $read['required']);
    }

    public function test_write_mode_keeps_input_names_defaults_and_omits_computed_fields(): void
    {
        $definition = $this->definition('purpose', Fixtures\SchemaPurposeData::class);
        $this->app->instance(ResourceRegistry::class, (new InMemoryResourceRegistry)->register($definition));
        $write = $this->getJson('frame/resources/purpose/schema')->assertOk()->json();
        $this->assertArrayHasKey('input_title', $write['properties']);
        $this->assertArrayNotHasKey('receipt', $write['properties']);
        $this->assertNotContains('input_title', $write['required'] ?? []);
    }

    public function test_read_purpose_reaches_configured_generator_and_address_resolver(): void
    {
        config()->set('data-schemas.generators', [NarrowSampleGenerator::class, JsonSchemaGenerator::class]);
        $this->getJson('frame/resources/sample/schema?purpose=read')->assertOk()->assertJsonPath('x-mode', 'response');
        $this->app->instance(ResourceSchemaResolver::class, new class implements ResourceSchemaResolver
        {
            public function schema(ResourceDefinition $resource, Request $request): ?array
            {
                return $resource->key === 'sample' ? ['title' => $request->query('collection').':'.$request->query('purpose')] : null;
            }
        });
        $this->getJson('frame/resources/sample/schema?collection=articles&purpose=read')->assertOk()->assertJsonPath('title', 'articles:read');
        $this->getJson('frame/resources/sample/schema?collection=people&purpose=write')->assertOk()->assertJsonPath('title', 'people:write');
        $this->getJson('frame/resources/contact/schema?purpose=read')->assertOk()->assertJsonStructure(['properties' => ['email']]);
    }

    public function test_reach_and_unknown_resource_precede_invalid_purpose_validation(): void
    {
        $this->getJson('frame/resources/sample/schema?purpose=other')->assertUnprocessable();
        $this->getJson('frame/resources/sample/schema?purpose[]=read')->assertUnprocessable();
        $this->getJson('frame/resources/missing/schema?purpose=other')->assertNotFound();
    }

    public function test_denied_reach_precedes_invalid_purpose_validation(): void
    {
        $this->app->instance(\Schemastud\Frame\Contracts\ResourceAccessGate::class, new class implements \Schemastud\Frame\Contracts\ResourceAccessGate
        {
            public function allowsResource(ResourceDefinition $definition): bool
            {
                return false;
            }
        });
        $this->getJson('frame/resources/sample/schema?purpose=other')->assertForbidden();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $registry = (new InMemoryResourceRegistry)
            ->register($this->definition('sample', SampleResourceData::class))
            ->register($this->definition('contact', ContactResourceData::class));

        $this->app->instance(ResourceRegistry::class, $registry);

        // The controller takes both host plugs by constructor injection and neither is bound by
        // default (a host binds its own), so resolving it needs them present even though `schema()`
        // touches neither — it reads only the registry. Mocks with no expectations, deliberately:
        // if this endpoint ever starts calling a handler, these fail loudly rather than absorbing it.
        $this->app->instance(FrameResourceHandlerResolver::class, Mockery::mock(FrameResourceHandlerResolver::class));
    }

    public function test_address_dependent_schema_and_null_fallthrough(): void
    {
        $this->app->instance(ResourceSchemaResolver::class,
            new class implements ResourceSchemaResolver
            {
                public function schema(ResourceDefinition $resource, Request $request): ?array
                {
                    return $resource->key === 'sample'
                        ? ['type' => 'object', 'properties' => ['fields' => ['type' => 'object', 'title' => $request->query('collection')]]]
                        : null;
                }
            });
        $this->getJson('frame/resources/sample/schema?collection=articles')->assertOk()->assertJsonPath('properties.fields.title', 'articles');
        $this->getJson('frame/resources/sample/schema?collection=people')->assertOk()->assertJsonPath('properties.fields.title', 'people');
        $this->getJson('frame/resources/contact/schema')->assertOk()->assertJsonStructure(['properties' => ['email']]);
    }

    private function definition(string $key, string $data): ResourceDefinition
    {
        return new ResourceDefinition(
            key: $key,
            model: SampleModel::class,
            data: $data,
            creatable: true,
            query: null,
            editData: null,
            policy: null,
            form: 'bare',
            nav: new NavMetadata(label: ucfirst($key)),
        );
    }

    /** The single-generator default — 13 of the estate's 14 hosts — is unchanged by the migration. */
    public function test_the_default_single_generator_host_still_gets_the_ordinary_schema(): void
    {
        $response = $this->getJson('frame/resources/sample/schema');

        $response->assertOk();
        $this->assertSame('object', $response->json('type'));
        $this->assertArrayHasKey('title', $response->json('properties'));
        $this->assertNull($response->json('x-generated-by'));
    }

    public function test_create_result_selection_does_not_change_the_input_schema(): void
    {
        $definition = $this->definition('sample', SampleResourceData::class)->withOverrides(
            editData: ContactResourceData::class,
            createResultData: SampleCreateResultData::class,
        );
        $this->app->instance(ResourceRegistry::class, (new InMemoryResourceRegistry)->register($definition));

        $response = $this->getJson('frame/resources/sample/schema')->assertOk();
        $this->assertArrayHasKey('email', $response->json('properties'));
        $this->assertArrayNotHasKey('receipt', $response->json('properties'));
        $this->assertArrayNotHasKey('record', $response->json('properties'));
    }

    /** The thingsontv shape: narrow generator FIRST, and it wins for a class it accepts. */
    public function test_a_multi_generator_host_dispatches_to_the_narrow_generator_configured_first(): void
    {
        config()->set('data-schemas.generators', [NarrowSampleGenerator::class, JsonSchemaGenerator::class]);

        $response = $this->getJson('frame/resources/sample/schema');

        // Hand-building JsonSchemaGenerator — what this endpoint did before — yields a schema with
        // no marker. Dispatch is the only thing that puts the narrow member's output here.
        $response->assertOk();
        $this->assertSame(NarrowSampleGenerator::class, $response->json('x-generated-by'));
    }

    /**
     * The request mode must reach the member that ultimately wins. `ChainedGenerator::forRequest()`
     * sets the mode on EVERY member rather than a pre-selected one, because the member is chosen per
     * class at `generate()` time — and each mode call returns a new CLONE, so the endpoint resolves
     * fresh and modes once rather than mutating a resolved instance.
     */
    public function test_the_request_mode_reaches_the_dispatched_member(): void
    {
        config()->set('data-schemas.generators', [NarrowSampleGenerator::class, JsonSchemaGenerator::class]);

        $response = $this->getJson('frame/resources/sample/schema');

        $this->assertSame('request', $response->json('x-mode'));
    }

    /**
     * ...and a class the narrow member refuses falls THROUGH to the general one, rather than being
     * handed to `generators[0]` merely because it is first.
     */
    public function test_a_class_the_narrow_generator_refuses_falls_through_to_the_general_one(): void
    {
        config()->set('data-schemas.generators', [NarrowSampleGenerator::class, JsonSchemaGenerator::class]);

        $response = $this->getJson('frame/resources/contact/schema');

        $response->assertOk();
        $this->assertNull($response->json('x-generated-by'));
        $this->assertSame('object', $response->json('type'));
    }
}
