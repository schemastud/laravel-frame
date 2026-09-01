<?php

namespace Schemastud\Frame\Tests;

use InvalidArgumentException;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryIndex;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\SampleModel;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;

/**
 * Registry-kernel ticket 38: `frame.resources` on the popcorn kernel.
 *
 * The tripwire first (27 D3): testbench does not auto-discover, so a harness that omits
 * `PopcornServiceProvider` hands every `make()` a FRESH index — every membership assertion below
 * would then pass over a throwaway. Assert the sharing before believing anything else.
 */
class ResourceRegistryConformanceTest extends TestCase
{
    public function test_it_shares_one_registry_index_across_the_container(): void
    {
        $this->assertSame(app(RegistryIndex::class), app(RegistryIndex::class));
    }

    public function test_the_port_extends_the_kernel_contract(): void
    {
        $this->assertTrue(is_subclass_of(ResourceRegistry::class, Registry::class));
        $this->assertInstanceOf(Registry::class, new InMemoryResourceRegistry);
    }

    public function test_it_declares_its_root_into_the_shared_index(): void
    {
        $roots = array_map(strval(...), app(RegistryIndex::class)->keys());

        $this->assertContains('frame.resources', $roots);
    }

    public function test_it_keeps_the_port_vocabulary_over_the_kernel(): void
    {
        $registry = (new InMemoryResourceRegistry)->register($this->definition('sample'));

        $this->assertTrue($registry->has('sample'));
        $this->assertInstanceOf(ResourceDefinition::class, $registry->get('sample'));
        $this->assertSame('sample', $registry->get('sample')->key);

        // The kernel spelling works too, which is what lets a Registrar fill this at all.
        $registry->register('other', $this->definition('other'));

        $this->assertTrue($registry->has('other'));
        $this->assertSame('other', $registry->get('other')->key);

        // Relative in, absolute out (20 D2) — and the caller-facing enumeration keeps the caller's
        // spelling AND its insertion order, which is what the served manifest promises.
        $this->assertSame(
            // `frame.resources.frame.*`, not `frame.resources.*`: since registry-kernel 77 this class is
            // a MEMBER of the `frame.resources` index, attached under `frame`, and its keyspace is the
            // member's — the index owns the root. Ticket 26 D2's runtime root, taken as an instance
            // declaration because the member segment is not knowable at class-attribute time.
            ['frame.resources.frame.sample', 'frame.resources.frame.other'],
            array_map(strval(...), $registry->keys()),
        );
        $this->assertSame(
            ['sample', 'other'],
            array_map(fn (ResourceDefinition $d) => $d->key, $registry->all()),
        );
    }

    public function test_get_keeps_the_exception_it_has_always_thrown(): void
    {
        // NOT the kernel's RegistryMiss: consumers may be catching this type, and a migration is not
        // supposed to change miss behaviour (brief §3b finding 5, as amended by ticket 61).
        $this->expectException(InvalidArgumentException::class);

        (new InMemoryResourceRegistry)->get('nope');
    }

    public function test_it_publishes_the_nullable_twin_for_a_key_that_came_from_outside(): void
    {
        $registry = (new InMemoryResourceRegistry)->register($this->definition('sample'));

        $this->assertInstanceOf(ResourceDefinition::class, $registry->find('sample'));
        $this->assertNull($registry->find('nope'));

        // The shape half of the same problem: a string that is not a legal key at all is a miss here,
        // not the InvalidRegistryKey the kernel's parser (rightly) raises at a declaration site. Both
        // of frame's own controllers reach these two methods with a raw URL segment.
        $this->assertNull($registry->find('Sample'));
        $this->assertNull($registry->find('a/b'));
        $this->assertNull($registry->find(''));

        $this->assertFalse($registry->has('Sample'));
        $this->assertFalse($registry->has('a/b'));
        $this->assertFalse($registry->has(''));
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
            form: 'raw',
            nav: new NavMetadata(
                label: 'Samples',
                group: 'Testing',
                section: 'lab',
                navOrder: 3,
                routeName: 'samples.index',
            ),
        );
    }
}
