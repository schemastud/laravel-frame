<?php

namespace Schemastud\Frame\Tests;

use InvalidArgumentException;
use Rushing\Popcorn\Registries\Key;
use Rushing\Popcorn\Registries\RegistryIndex;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Registry\CompositeResourceRegistry;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\RestOnlyProducerFixture;
use Schemastud\Frame\Tests\Fixtures\SampleModel;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;

/**
 * Registry-kernel ticket 77 — `frame.resources` is an INDEX whose entries are resource registries.
 *
 * The tripwire first, same as its sibling: testbench does not auto-discover, so a harness missing
 * `PopcornServiceProvider` hands every `make()` a fresh index and every membership assertion below
 * would pass over a throwaway.
 */
class CompositeResourceRegistryTest extends TestCase
{
    public function test_the_container_and_the_index_answer_with_the_sam_e_object(): void
    {
        // This is ticket 77's premise, inverted into a guard. Before it, `app(port)` was the producer's
        // registry and `ownerOf('frame.resources')` was a freshly-constructed empty default — two
        // objects, one of them lying by looking honest.
        $this->assertSame(app(RegistryIndex::class), app(RegistryIndex::class));

        $port = app(ResourceRegistry::class);

        $this->assertInstanceOf(CompositeResourceRegistry::class, $port);
        $this->assertSame($port, app(RegistryIndex::class)->ownerOf(Key::of('frame.resources')));
    }

    public function test_the_root_holds_member_s_and_never_reads_empty_at_a_host_with_a_producer(): void
    {
        $port = app(ResourceRegistry::class);

        $this->assertSame(['frame.resources.frame'], array_map(strval(...), $port->keys()));

        $port->attach('producer', $this->producer('producer', ['plans']));

        $this->assertSame(
            ['frame.resources.frame', 'frame.resources.producer'],
            array_map(strval(...), $port->keys()),
        );

        // The entry IS the registry — 26 D1's registry-as-entry, and the thing that makes "who supplies
        // frame's resources" addressable rather than a container alias nothing can see.
        $this->assertInstanceOf(ResourceRegistry::class, $port->resolve('producer'));
    }

    public function test_two_producers_are_expressible_and_neither_silently_overwrites_the_other(): void
    {
        $port = app(ResourceRegistry::class);

        $port->attach('first', $this->producer('first', ['plans', 'only_first']));
        $port->attach('second', $this->producer('second', ['plans', 'only_second']));

        // Both members survive as entries — the container alias this replaces kept exactly one.
        $this->assertSame(
            ['frame.resources.frame', 'frame.resources.first', 'frame.resources.second'],
            array_map(strval(...), $port->keys()),
        );

        // Each member's own copy is still readable at its own address, so the displaced definition is
        // recoverable rather than lost.
        $this->assertTrue($port->resolve('first')->has('only_first'));
        $this->assertTrue($port->resolve('second')->has('only_second'));

        // The shared key resolves by the declared rule: LAST attached wins.
        $this->assertSame('second', $port->get('plans')->nav->label);
        $this->assertTrue($port->has('only_first'));
        $this->assertTrue($port->has('only_second'));

        // …and the manifest unions them, one row per key.
        $this->assertSame(
            ['plans', 'only_first', 'only_second'],
            array_map(fn (ResourceDefinition $d) => $d->key, $port->all()),
        );
    }

    public function test_a_members_own_projection_semantics_survive_the_index(): void
    {
        // The one thing ticket 77 forbids flattening. `RestOnlyProducer` answers `false` from the PORT
        // half for a resource its own STORE holds — beam's adapter is the live instance of this, where a
        // REST-only particle resource has no frame projection. The index must route, never re-decide.
        $port = app(ResourceRegistry::class);
        $port->attach('rest_only', new RestOnlyProducerFixture($this->definition('hidden')));

        $this->assertFalse($port->has('hidden'));
        $this->assertNull($port->find('hidden'));
        $this->assertSame([], $port->all());

        // The escape hatch answers the store's own question, THROUGH the index and deeply — one level
        // would hand back the filtered member and read as agreement.
        $this->assertTrue($port->unfiltered()->has('hidden'));
    }

    public function test_the_imperative_write_still_lands_at_a_frame_only_host(): void
    {
        // `app(ResourceRegistry::class)->register($definition)` is the historical spelling, and it must
        // keep working — it forwards to the default member rather than routing.
        $port = app(ResourceRegistry::class);
        $port->register($this->definition('plans'));

        $this->assertTrue($port->has('plans'));
        $this->assertSame('plans', $port->get('plans')->key);

        // Twice, because the default member is attached as a LAZY closure: a closure that constructed
        // rather than resolving a container singleton would hand back a fresh empty store here and lose
        // the write above without any error.
        $port->register($this->definition('other'));

        $this->assertSame(['plans', 'other'], array_map(fn (ResourceDefinition $d) => $d->key, $port->all()));
    }

    public function test_has_is_the_port_s_question_and_keys_is_the_kernels(): void
    {
        // Deliberate asymmetry, pinned so it cannot drift into an accident: `has()` asks about a
        // RESOURCE (what `FrameResourceController` calls it with) and `keys()` lists MEMBERS.
        $port = app(ResourceRegistry::class);
        $port->register($this->definition('plans'));

        $this->assertSame(['frame.resources.frame'], array_map(strval(...), $port->keys()));
        $this->assertFalse($port->has('frame.resources.frame'));
        $this->assertTrue($port->has('plans'));
    }

    public function test_get_keeps_the_exception_the_port_has_always_thrown(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ResourceRegistry::class)->get('nope');
    }

    /** @param  list<string>  $keys */
    private function producer(string $member, array $keys): InMemoryResourceRegistry
    {
        $registry = new InMemoryResourceRegistry($member);

        foreach ($keys as $key) {
            // The nav label carries the member's name so a shared key can be told apart by WHICH
            // producer answered — the whole question the arity ruling decides.
            $registry->register($this->definition($key, $member));
        }

        return $registry;
    }

    private function definition(string $key, ?string $label = null): ResourceDefinition
    {
        return new ResourceDefinition(
            key: $key,
            model: SampleModel::class,
            data: SampleResourceData::class,
            creatable: true,
            query: null,
            editData: null,
            policy: null,
            form: 'raw',
            nav: new NavMetadata(label: $label ?? $key),
        );
    }
}
