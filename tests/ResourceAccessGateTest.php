<?php

namespace Schemastud\Frame\Tests;

use Illuminate\Foundation\Auth\User;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Schemastud\Frame\Contracts\FrameResourceHandler;
use Schemastud\Frame\Contracts\FrameResourceHandlerResolver;
use Schemastud\Frame\Contracts\ResourceAccessGate;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Registry\ResourceDefinition as Definition;
use Schemastud\Frame\Tests\Fixtures\SampleModel;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;

/**
 * The REACH axis of `{prefix}/resources/*` — the question asked BEFORE the per-verb policy question:
 * may this actor address this resource on the socket at all?
 *
 * Measured 2026-09-12 at `https://fresh-tower.test` signed in as `demo-member` (an ordinary tenant
 * user, `os.operate` = false): `GET /frame/resources/users`, `/frame/resources/teams` and
 * `/frame/resources/tenants` all answered **200** with every user's email, every team, and the tenant
 * roster with its owner email. One socket serves every realm, `config('frame.realms')` was a
 * projection list the nav read and nothing enforced, and `config('frame.middleware')` is ONE ambient
 * list (`['web','auth']` at every starter) that cannot distinguish one resource from another. So no
 * value of any existing key could refuse the operator realm's resources without refusing the tenant
 * realm's too.
 *
 * The gate is a PORT, not a rule: frame has no realm concept to enforce (its `RealmDefinition` is a
 * shape the foundation carries for others to populate), so the producer above frame — beam's
 * `RealmEntitlementResourceGate` — answers, and frame ships {@see \Schemastud\Frame\Authorization\OpenResourceAccessGate}
 * so a frame-only host is byte-for-byte what it was.
 *
 * The assertions below fail if the gate is dropped, if it is asked on only the write axis (the
 * READ verbs are the ones that leaked), or if it is asked after the handler rather than before it.
 */
class ResourceAccessGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $registry = (new InMemoryResourceRegistry)
            ->register($this->definition('closed'))
            ->register($this->definition('open'));

        $this->app->instance(ResourceRegistry::class, $registry);

        // No expectations. A handler reached at all on a refused resource is a gate that did not
        // fire, and Mockery fails that loudly rather than absorbing it into a 200.
        $this->app->instance(
            FrameResourceHandlerResolver::class,
            Mockery::mock(FrameResourceHandlerResolver::class)
        );

        $this->app->instance(ResourceAccessGate::class, new class implements ResourceAccessGate
        {
            public function allowsResource(Definition $definition): bool
            {
                return $definition->key !== 'closed';
            }
        });
    }

    private function definition(string $key): ResourceDefinition
    {
        return new ResourceDefinition(
            key: $key,
            model: SampleModel::class,
            data: SampleResourceData::class,
            creatable: true,
            query: null,
            editData: null,
            policy: null,
            form: 'bare',
            nav: new NavMetadata(label: ucfirst($key)),
        );
    }

    private function actor(): User
    {
        return new class extends User
        {
            protected $table = 'users';

            public function getAuthIdentifier(): int
            {
                return 1;
            }
        };
    }

    /**
     * The defect itself, verb by verb. `index` is the one that leaked the roster; `show` is the
     * one that leaked a named row; `schema` discloses the shape of a surface the actor may not
     * reach. All three were untouched by the 2026-09-05 write-axis gate.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function refusedVerbs(): array
    {
        return [
            'index' => ['getJson', 'frame/resources/closed'],
            'show' => ['getJson', 'frame/resources/closed/records/1'],
            'schema' => ['getJson', 'frame/resources/closed/schema'],
            'filter-schema' => ['getJson', 'frame/resources/closed/filters/schema'],
            'summary' => ['getJson', 'frame/resources/closed/summary'],
            'store' => ['postJson', 'frame/resources/closed'],
            'update' => ['putJson', 'frame/resources/closed/records/1'],
            'destroy' => ['deleteJson', 'frame/resources/closed/records/1'],
        ];
    }

    #[DataProvider('refusedVerbs')]
    public function test_a_refused_resource_is_forbidden_on_every_socket_verb(string $method, string $url): void
    {
        $this->actingAs($this->actor())->{$method}($url)->assertForbidden();
    }

    /** …and a permitted resource still reaches its handler, or the gate is just a wall. */
    public function test_a_permitted_resource_still_reaches_the_handler(): void
    {
        $handler = new class implements FrameResourceHandler
        {
            public bool $reached = false;

            public function index(ResourceDefinition $definition, array $params): array
            {
                $this->reached = true;

                return [];
            }

            public function show(ResourceDefinition $definition, string $id): array
            {
                $this->reached = true;

                return ['id' => $id];
            }

            public function store(ResourceDefinition $definition, array $input): array
            {
                return ['id' => '1'];
            }

            public function update(ResourceDefinition $definition, string $id, array $input): array
            {
                return ['id' => $id];
            }

            public function destroy(ResourceDefinition $definition, string $id): void {}
        };

        $resolver = Mockery::mock(FrameResourceHandlerResolver::class);
        $resolver->shouldReceive('handlerFor')->andReturn($handler);
        $this->app->instance(FrameResourceHandlerResolver::class, $resolver);

        $this->actingAs($this->actor())->getJson('frame/resources/open')->assertOk();
        $this->assertTrue($handler->reached, 'index never reached the handler');

        $handler->reached = false;
        $this->actingAs($this->actor())->getJson('frame/resources/open/records/7')->assertOk();
        $this->assertTrue($handler->reached, 'show never reached the handler');
    }

    /**
     * Frame's own default is OPEN, and that is the whole reason this is a port. A frame-only host
     * has no realm concept for the gate to consult, so binding a refusing default here would close
     * every socket in the estate on a question nobody asked.
     */
    public function test_the_shipped_default_gate_permits(): void
    {
        $this->app->forgetInstance(ResourceAccessGate::class);
        $this->refreshApplication();

        $this->assertTrue(
            $this->app->make(ResourceAccessGate::class)->allowsResource($this->definition('closed'))
        );
    }
}
