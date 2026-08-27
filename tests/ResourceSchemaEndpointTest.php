<?php

namespace Schemastud\Frame\Tests;

use Mockery;
use Schemastud\DataSchemas\Generators\JsonSchemaGenerator;
use Schemastud\Frame\Contracts\FrameFilterProvider;
use Schemastud\Frame\Contracts\FrameResourceHandlerResolver;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\ContactResourceData;
use Schemastud\Frame\Tests\Fixtures\NarrowSampleGenerator;
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
        $this->app->instance(FrameFilterProvider::class, Mockery::mock(FrameFilterProvider::class));
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
