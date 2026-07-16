<?php

namespace Schemastud\Frame\Tests;

use InvalidArgumentException;
use Schemastud\Frame\Attributes\AdminResource;
use Schemastud\Frame\Registry\AdminResourceDefinition;
use Schemastud\Frame\Registry\AdminResourceRegistry;
use Schemastud\Frame\Tests\Fixtures\FixtureUnionSource;
use Schemastud\Frame\Tests\Fixtures\ReviewFixtureResourceData;
use Schemastud\Frame\Tests\Fixtures\SampleModel;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;
use Spatie\LaravelData\Data;

/**
 * Ticket 21 (§1/§2) — the registration branch + invariant: a `source`-backed union
 * resource registers as `sourceKind:'service'` (model nulled, editData/query nulled,
 * not creatable); a `model`-backed one keeps today's behavior; both/neither throws.
 */
class UnionResourceRegistrationTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('frame.resources', [
            SampleResourceData::class,
            ReviewFixtureResourceData::class,
        ]);
    }

    public function test_source_backed_resource_registers_as_a_service_union(): void
    {
        $def = $this->app->make(AdminResourceRegistry::class)->get('review-fixture');

        $this->assertSame('service', $def->sourceKind);
        $this->assertNull($def->model);
        $this->assertSame(FixtureUnionSource::class, $def->source);
        $this->assertFalse($def->creatable);
        $this->assertNull($def->editData);
        $this->assertNull($def->query);
        $this->assertSame('review', $def->policy);
        $this->assertSame('Review', $def->nav->label);
    }

    public function test_model_backed_resource_still_registers_as_a_model_source(): void
    {
        $def = $this->app->make(AdminResourceRegistry::class)->get('sample');

        $this->assertSame('model', $def->sourceKind);
        $this->assertSame(SampleModel::class, $def->model);
        $this->assertNull($def->source);
        $this->assertTrue($def->creatable);
    }

    public function test_setting_both_model_and_source_throws(): void
    {
        $registry = new AdminResourceRegistry;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly one of `model` or `source`');

        $registry->registerClass(BothModelAndSourceFixture::class);
    }

    public function test_setting_neither_model_nor_source_throws(): void
    {
        $registry = new AdminResourceRegistry;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly one of `model` or `source`');

        $registry->registerClass(NeitherModelNorSourceFixture::class);
    }

    public function test_definition_lazily_resolves_the_source_from_the_container(): void
    {
        $def = $this->app->make(AdminResourceRegistry::class)->get('review-fixture');

        $this->assertInstanceOf(FixtureUnionSource::class, $def->resolveSource());
    }

    public function test_resolving_a_source_on_a_model_backed_resource_throws(): void
    {
        $def = AdminResourceDefinition::fromAttribute(
            SampleResourceData::class,
            new AdminResource(key: 'x', label: 'X', model: SampleModel::class),
        );

        $this->expectException(InvalidArgumentException::class);

        $def->resolveSource();
    }
}

#[AdminResource(key: 'both', label: 'Both', model: SampleModel::class, source: FixtureUnionSource::class)]
class BothModelAndSourceFixture extends Data
{
    public function __construct(public string $id = '') {}
}

#[AdminResource(key: 'neither', label: 'Neither')]
class NeitherModelNorSourceFixture extends Data
{
    public function __construct(public string $id = '') {}
}
