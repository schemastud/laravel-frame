<?php

namespace Schemastud\Frame\Tests;

use Schemastud\Frame\Registry\ContextManifest;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\ContactResourceData;
use Schemastud\Frame\Tests\Fixtures\SampleModel;

/**
 * WHERE a resource's create affordance lives — the declared `createAffordance` slot, the
 * `creatable` gate it is resolved against, and the single value that reaches the client.
 *
 * The census behind it: nine list surfaces at the flagship pass `Toolbar: () => null` to frame's
 * ListShell, and FIVE of those nine name a resource whose declaration already says
 * `creatable: false` (`concepts`, `rules`, `declarations`, `evidence`, `fragments`). The answer
 * was on the wire the whole time — `GET /frame/manifest` has always served `creatable` — it just
 * had no route to a shell, because a shell is handed a ContextManifest and never a
 * ResourceDefinition. That is why the resolved value rides the manifest block, exactly as
 * `layout` does.
 */
class CreateAffordanceTest extends TestCase
{
    private function definition(bool $creatable, string $createAffordance = 'frame'): ResourceDefinition
    {
        return new ResourceDefinition(
            key: 'sample',
            model: SampleModel::class,
            data: ContactResourceData::class,
            creatable: $creatable,
            query: null,
            editData: null,
            policy: null,
            form: 'bare',
            nav: new NavMetadata(label: 'Samples'),
            createAffordance: $createAffordance,
        );
    }

    public function test_the_slot_defaults_to_frame_so_every_existing_declaration_is_unchanged(): void
    {
        $this->assertSame('frame', $this->definition(creatable: true)->createAffordance);
    }

    public function test_a_creatable_resource_declaring_nothing_keeps_frames_own_affordance(): void
    {
        $this->assertSame('frame', $this->definition(creatable: true)->resolvedCreateAffordance());
    }

    public function test_a_creatable_resource_may_declare_the_affordance_is_the_hosts(): void
    {
        $this->assertSame(
            'host',
            $this->definition(creatable: true, createAffordance: 'host')->resolvedCreateAffordance(),
        );
    }

    /**
     * The half that needs no new declaration anywhere. `creatable: false` already means there is no
     * create here, so frame emitting a "New …" button for it was a door onto a 405 — which is what
     * five flagship pages were deleting by hand.
     */
    public function test_a_non_creatable_resource_resolves_to_host_without_declaring_anything(): void
    {
        $this->assertSame('host', $this->definition(creatable: false)->resolvedCreateAffordance());
    }

    /**
     * `creatable` is the gate and it is not overridable by a PRESENTATION slot. A declaration saying
     * "frame owns the create button" on a resource that cannot be created is a contradiction, and the
     * capability wins — the alternative is a slot that can re-open a closed write path by talking
     * about layout.
     */
    public function test_the_presentation_slot_cannot_re_open_a_closed_create(): void
    {
        $this->assertSame(
            'host',
            $this->definition(creatable: false, createAffordance: 'frame')->resolvedCreateAffordance(),
        );
    }

    public function test_the_copy_wither_carries_the_slot_and_can_overlay_it(): void
    {
        $base = $this->definition(creatable: true, createAffordance: 'host');

        $this->assertSame('host', $base->withOverrides(policy: 'anything')->createAffordance);
        $this->assertSame('frame', $base->withOverrides(createAffordance: 'frame')->createAffordance);
    }

    public function test_the_manifest_block_carries_the_resolved_value_for_the_shells(): void
    {
        $block = (new ContextManifest)->forResource(
            ContactResourceData::class,
            null,
            'sample',
            $this->definition(creatable: false)->resolvedCreateAffordance(),
        );

        $this->assertSame('host', $block['createAffordance']);
    }

    /**
     * Zero migration: every pre-existing caller of `forResource()` passes three arguments and must
     * still emit the block that renders frame's own toolbar.
     */
    public function test_an_unaware_caller_still_gets_frames_own_affordance(): void
    {
        $block = (new ContextManifest)->forResource(ContactResourceData::class);

        $this->assertSame('frame', $block['createAffordance']);
    }
}
