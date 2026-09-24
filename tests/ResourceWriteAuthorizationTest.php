<?php

namespace Schemastud\Frame\Tests;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Schemastud\Frame\Contracts\FrameResourceHandler;
use Schemastud\Frame\Contracts\FrameResourceHandlerResolver;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Contracts\WriteSubjectResolver;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\SampleCreateResultData;
use Schemastud\Frame\Tests\Fixtures\SampleModel;
use Schemastud\Frame\Tests\Fixtures\SamplePolicy;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;
use Schemastud\Frame\Tests\Fixtures\UnpolicedModel;

/**
 * The WRITE axis of `{prefix}/resources/*` — the gate the socket shipped without.
 *
 * Measured at `~/Herd/beam` on 2026-09-05, signed in as a member holding only `beam-ux-entry.view`:
 * `POST /frame/resources/beam-ux-entry` with an empty body answered **422**. The 422 is the whole
 * finding — validation ran, which means the request got all the way into a handler with no
 * permission question asked anywhere before it. `FrameResourceController` contained zero occurrences
 * of `authorize`, `Gate::` or any ability name.
 *
 * The assertions below are written to fail if the gate is removed OR if it is moved back behind
 * validation: `assertForbidden()` on a request whose body would ALSO fail validation only holds
 * while the gate runs first.
 */
class ResourceWriteAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SamplePolicy::reset();
        Gate::policy(SampleModel::class, SamplePolicy::class);

        Schema::create('sample_models', function ($table) {
            $table->id();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        SampleModel::create(['title' => 'existing']);

        $registry = (new InMemoryResourceRegistry)
            ->register($this->definition('sample', SampleModel::class))
            ->register($this->definition('orphan', UnpolicedModel::class))
            ->register($this->definition('union', null));

        $this->app->instance(ResourceRegistry::class, $registry);

        // No expectations set. A handler reached at all is a gate that did not fire, and Mockery
        // fails that loudly rather than absorbing it into a 200.
        $this->app->instance(
            FrameResourceHandlerResolver::class,
            Mockery::mock(FrameResourceHandlerResolver::class)
        );
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.default', 'testing');
    }

    public function test_invalid_variant_selection_is_rejected_before_the_resource_handler(): void
    {
        $this->getJson('/frame/resources/sample?filterVariant[]=invalid')
            ->assertUnprocessable()->assertJsonValidationErrors('filterVariant');
    }

    private function definition(string $key, ?string $model): ResourceDefinition
    {
        return new ResourceDefinition(
            key: $key,
            model: $model,
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

    /** Bind a handler that records whether it was reached, for the allow-path assertions. */
    private function expectHandlerReached(?array $createResult = null): object
    {
        $handler = new class($createResult) implements FrameResourceHandler
        {
            public bool $reached = false;

            public function __construct(private ?array $createResult) {}

            public function index(ResourceDefinition $definition, array $params): array
            {
                return [];
            }

            public function show(ResourceDefinition $definition, string $id): array
            {
                return ['id' => $id];
            }

            public function store(ResourceDefinition $definition, array $input): array
            {
                $this->reached = true;

                return $this->createResult ?? ['id' => '1'];
            }

            public function update(ResourceDefinition $definition, string $id, array $input): array
            {
                $this->reached = true;

                return ['id' => $id];
            }

            public function destroy(ResourceDefinition $definition, string $id): void
            {
                $this->reached = true;
            }
        };

        $resolver = Mockery::mock(FrameResourceHandlerResolver::class);
        $resolver->shouldReceive('handlerFor')->andReturn($handler);
        $this->app->instance(FrameResourceHandlerResolver::class, $resolver);

        return $handler;
    }

    // ---- the defect itself -----------------------------------------------------------------

    /**
     * The exact shape measured at the host: a signed-in actor the policy denies gets 403, and
     * critically NOT the 422 that proved the request had reached validation.
     */
    public function test_an_authenticated_actor_the_policy_denies_is_forbidden_from_creating(): void
    {
        $response = $this->actingAs($this->actor())->postJson('frame/resources/sample', []);

        $response->assertForbidden();
    }

    public function test_a_denied_actor_is_forbidden_from_updating(): void
    {
        $this->actingAs($this->actor())
            ->putJson('frame/resources/sample/records/1', ['title' => 'x'])
            ->assertForbidden();
    }

    public function test_a_denied_actor_is_forbidden_from_deleting(): void
    {
        $this->actingAs($this->actor())
            ->deleteJson('frame/resources/sample/records/1')
            ->assertForbidden();
    }

    /** …and the permitted actor still gets through to the handler, or the gate is just a wall. */
    public function test_a_permitted_actor_still_reaches_the_handler_on_every_write_verb(): void
    {
        SamplePolicy::$allows = ['create', 'update', 'delete'];

        $handler = $this->expectHandlerReached();

        $this->actingAs($this->actor())->postJson('frame/resources/sample', [])->assertOk();
        $this->assertTrue($handler->reached, 'store never reached the handler');

        $handler->reached = false;
        $this->actingAs($this->actor())->putJson('frame/resources/sample/records/1', [])->assertOk();
        $this->assertTrue($handler->reached, 'update never reached the handler');

        $handler->reached = false;
        $this->actingAs($this->actor())->deleteJson('frame/resources/sample/records/1')->assertNoContent();
        $this->assertTrue($handler->reached, 'destroy never reached the handler');
    }

    public function test_a_custom_create_result_is_returned_intact_after_authorization(): void
    {
        $definition = $this->definition('sample', SampleModel::class)->withOverrides(
            createResultData: SampleCreateResultData::class,
        );
        $this->app->instance(ResourceRegistry::class, (new InMemoryResourceRegistry)->register($definition));
        $result = new SampleCreateResultData(new SampleResourceData('Created'), 'one-time-receipt');
        $handler = $this->expectHandlerReached($result->toArray());

        $this->actingAs($this->actor())->postJson('frame/resources/sample', [])->assertForbidden();
        $this->assertFalse($handler->reached);

        SamplePolicy::$allows = ['create', 'update'];
        $this->postJson('frame/resources/sample', ['title' => 'Created'])->assertOk()
            ->assertExactJson(['data' => $result->toArray()]);
        $this->assertTrue($handler->reached);
        $this->putJson('frame/resources/sample/records/1', [])->assertOk()
            ->assertExactJson(['data' => ['id' => '1']]);
    }

    /**
     * The abilities are INDEPENDENT. A policy that allows `create` and refuses `delete` must be
     * honoured both ways — this is what fails if the gate ever collapses to one "may write" answer.
     */
    public function test_the_three_abilities_are_asked_separately(): void
    {
        SamplePolicy::$allows = ['create'];

        $this->expectHandlerReached();

        $this->actingAs($this->actor())->postJson('frame/resources/sample', [])->assertOk();
        $this->actingAs($this->actor())->deleteJson('frame/resources/sample/records/1')->assertForbidden();
    }

    // ---- fail closed -----------------------------------------------------------------------

    /**
     * A model-backed resource with NO policy is REFUSED on write. This is the deliberate opposite of
     * the read posture beside it (`FrameResourcesInvocable::resourceViewable()` keeps such a resource
     * visible in nav), and it is the estate's stated rule made real: *"A missing policy on a WRITE
     * stays denied."* Before this, `policy: null` was spelled the same as "deliberately open".
     */
    public function test_a_model_backed_resource_with_no_policy_is_refused_on_write(): void
    {
        $this->actingAs($this->actor())->postJson('frame/resources/orphan', [])->assertForbidden();
        $this->actingAs($this->actor())->deleteJson('frame/resources/orphan/records/1')->assertForbidden();
    }

    /** A service-backed union has no Eloquent subject for a policy to be about, so frame refuses it. */
    public function test_a_model_less_resource_is_refused_on_write(): void
    {
        $this->actingAs($this->actor())->postJson('frame/resources/union', [])->assertForbidden();
    }

    /**
     * An anonymous writer is denied even where the policy would allow the ability, because the
     * policy's `Authenticatable` first argument auto-denies a guest. Inherited deny-by-default —
     * asserted so a later refactor that starts passing a resolved-or-null actor cannot quietly
     * open it.
     */
    public function test_an_anonymous_writer_is_denied_even_when_the_ability_is_allowed(): void
    {
        SamplePolicy::$allows = ['create', 'update', 'delete'];

        $this->postJson('frame/resources/sample', [])->assertForbidden();
        $this->deleteJson('frame/resources/sample/records/1')->assertForbidden();
    }

    /**
     * A policy-less write is still refused BY THE GATE, so the host's `Gate::before` superuser passes it
     * and nobody else does. Root's plan create and conduit edit at `~/Herd/splicewire-app` (2026-09-24)
     * were 403 because this arm returned `false` beside the Gate instead of asking it.
     */
    public function test_a_gate_before_superuser_may_write_a_policy_less_model_and_nobody_else_may(): void
    {
        $superuser = $this->actor();
        Gate::before(fn ($user) => $user === $superuser ? true : null);
        $handler = $this->expectHandlerReached();

        $this->actingAs($superuser)->postJson('frame/resources/orphan', [])->assertOk();
        $this->actingAs($superuser)->deleteJson('frame/resources/orphan/records/1')->assertNoContent();
        $this->assertTrue($handler->reached);

        $handler->reached = false;
        $this->actingAs($this->actor())->postJson('frame/resources/orphan', [])->assertForbidden();
        $this->assertFalse($handler->reached);

        // A model-less resource has no subject for any callback to be about: still refused.
        $this->actingAs($superuser)->postJson('frame/resources/union', [])->assertForbidden();
    }

    /**
     * A lookup the database refuses reads as "no such record", not a 500. Postgres raises on a word
     * against a uuid key: Root's `PUT …/circuit-runs/records/anything` was a 500 here before the handler
     * could 405 it (splicewire-app 2026-09-24). The raise is contained in a savepoint, so the surrounding
     * transaction keeps working. (sqlite raises on a missing table instead, the same QueryException.)
     */
    public function test_a_lookup_the_database_refuses_reads_as_no_record(): void
    {
        Gate::policy(SampleMissingTableModel::class, SamplePolicy::class);
        $this->app->instance(ResourceRegistry::class, (new InMemoryResourceRegistry)
            ->register($this->definition('missing', SampleMissingTableModel::class)));
        SamplePolicy::$allows = ['update'];
        $this->expectHandlerReached();

        DB::beginTransaction();
        $this->actingAs($this->actor())->putJson('frame/resources/missing/records/not-a-uuid', [])->assertOk();

        $this->assertFalse(SamplePolicy::$asked[0]->exists);
        $this->assertSame(1, SampleModel::query()->count(), 'the surrounding transaction was poisoned');
        DB::rollBack();
    }

    /**
     * With a bound {@see WriteSubjectResolver} the policy is asked about the record WITHIN the resource's
     * scope; an id outside it resolves to nothing here, the policy sees the class-level probe, and the
     * handler's scoped lookup owns the answer (a 404 at a real host) instead of this lookup's 403.
     */
    public function test_a_bound_write_subject_resolver_decides_what_the_policy_is_asked_about(): void
    {
        SamplePolicy::$allows = ['delete'];
        $this->app->instance(WriteSubjectResolver::class, new class implements WriteSubjectResolver
        {
            public function resolve(ResourceDefinition $definition, string $id): ?Model
            {
                return null; // row 1 exists, but not within this caller's reach
            }
        });
        $this->expectHandlerReached();

        $this->actingAs($this->actor())->deleteJson('frame/resources/sample/records/1')->assertNoContent();

        $this->assertCount(1, SamplePolicy::$asked);
        $this->assertFalse(SamplePolicy::$asked[0]->exists, 'the out-of-scope row was resolved anyway');
    }

    /** Unbound, frame keeps its own unscoped lookup and asks about the persisted row. */
    public function test_without_a_resolver_the_policy_is_asked_about_the_persisted_row(): void
    {
        SamplePolicy::$allows = ['delete'];
        $this->expectHandlerReached();

        $this->actingAs($this->actor())->deleteJson('frame/resources/sample/records/1')->assertNoContent();

        $this->assertTrue(SamplePolicy::$asked[0]->exists);
    }

    // ---- the read axis must not move -------------------------------------------------------

    /**
     * The write gate must not leak sideways. `schema` is a READ on the same resource whose writes
     * are all denied above, and it stays 200 — a resource whose index leans on its row scope rather
     * than a class policy keeps working, which is the whole reason the two axes have opposite
     * defaults.
     */
    public function test_the_read_axis_is_untouched_by_the_write_gate(): void
    {
        $this->actingAs($this->actor())->getJson('frame/resources/sample/schema')->assertOk();
        $this->getJson('frame/resources/orphan/schema')->assertOk();
    }

    // ---- the client half -------------------------------------------------------------------

    /**
     * The manifest carries the ACTOR's capabilities, not just the resource's flags. Both resources
     * here declare `creatable: true`; only the actor axis separates them, and without this map the
     * console renders a "New entry" button and a row of "Delete entry" buttons to someone who holds
     * nothing but `view`.
     */
    public function test_the_manifest_carries_the_actors_capabilities_per_resource(): void
    {
        SamplePolicy::$allows = ['create'];

        $response = $this->actingAs($this->actor())->getJson('frame/manifest');

        $response->assertOk();
        $this->assertTrue($response->json('contexts.sample.can.create'));
        $this->assertFalse($response->json('contexts.sample.can.update'));
        $this->assertFalse($response->json('contexts.sample.can.delete'));

        // Same declaration, no policy — `creatable` says yes and the actor axis says no.
        $this->assertFalse($response->json('contexts.orphan.can.create'));
    }

    /** An anonymous reader is offered nothing, so the affordances match what the endpoint will do. */
    public function test_an_anonymous_manifest_offers_no_write_affordance(): void
    {
        SamplePolicy::$allows = ['create', 'update', 'delete'];

        $response = $this->getJson('frame/manifest');

        $response->assertOk();
        $this->assertFalse($response->json('contexts.sample.can.create'));
        $this->assertFalse($response->json('contexts.sample.can.delete'));
    }

    /**
     * The capability map is bounded ABOVE by the resource declaration: a resource nobody may delete
     * cannot report `can.delete` true just because the actor holds the ability. The resource axis
     * and the actor axis both have to say yes.
     */
    public function test_a_capability_cannot_exceed_the_resource_declaration(): void
    {
        SamplePolicy::$allows = ['create', 'update', 'delete'];

        $registry = (new InMemoryResourceRegistry)->register(
            $this->definition('sample', SampleModel::class)->withOverrides(deletable: false)
        );
        $this->app->instance(ResourceRegistry::class, $registry);

        $response = $this->actingAs($this->actor())->getJson('frame/manifest');

        $this->assertTrue($response->json('contexts.sample.can.create'));
        $this->assertFalse($response->json('contexts.sample.can.delete'));
    }
}

/** A model whose table does not exist, so any lookup raises a QueryException. */
class SampleMissingTableModel extends Model
{
    use HasUuids;

    protected $table = 'sample_missing_models';

    protected $guarded = [];
}
