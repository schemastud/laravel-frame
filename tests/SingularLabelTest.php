<?php

namespace Schemastud\Frame\Tests;

use Schemastud\Frame\Registry\ContextManifest;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\ContactResourceData;
use Schemastud\Frame\Tests\Fixtures\SampleModel;

/**
 * What a resource calls ONE record, and how that word reaches a shell.
 *
 * Frame's own list toolbar has been rendering the resource KEY — "New scaffold-packs" — because
 * the key was the only noun it had: a shell is handed a {@see ContextManifest} and never a
 * {@see ResourceDefinition}, so it can see neither the display label nor an inflector. Two
 * flagship pages re-implemented the entire Toolbar slot for no reason other than to say
 * "New pack" instead.
 *
 * ⚠️ It is a WORD, not a gate, and the tests below assert that in both directions. A display
 * noun that could suppress or resurrect a create affordance would be the estate's recurring
 * defect — a presentation default silently revoking a different declaration — wearing a new
 * name. `creatable` and `createAffordance` keep answering that question alone.
 */
class SingularLabelTest extends TestCase
{
    private function definition(string $key, string $label, string $singularLabel = ''): ResourceDefinition
    {
        return new ResourceDefinition(
            key: $key,
            model: SampleModel::class,
            data: ContactResourceData::class,
            creatable: true,
            query: null,
            editData: null,
            policy: null,
            form: 'bare',
            nav: new NavMetadata(label: $label),
            singularLabel: $singularLabel,
        );
    }

    public function test_the_plural_display_label_is_inflected_when_nothing_is_declared(): void
    {
        $this->assertSame(
            'Scaffold pack',
            $this->definition('scaffold-packs', 'Scaffold packs')->resolvedSingularLabel(),
        );
    }

    /**
     * The reason the slot exists at all, and the reason inflection alone is not the answer:
     * `media` singularizes to "Medium".
     */
    public function test_the_declared_word_wins_over_the_inflector_that_mangles_it(): void
    {
        $this->assertSame(
            'Medium',
            $this->definition('media', 'Media')->resolvedSingularLabel(),
            'guard: the inflector really does mangle this noun, which is what the slot is for',
        );

        $this->assertSame(
            'Media',
            $this->definition('media', 'Media', singularLabel: 'Media')->resolvedSingularLabel(),
        );
    }

    public function test_a_resource_with_no_label_at_all_falls_back_to_its_key(): void
    {
        $this->assertSame(
            'Scaffold Pack',
            $this->definition('scaffold-packs', '')->resolvedSingularLabel(),
        );
    }

    /**
     * The plural label is what NAV renders and it keeps exactly one spelling. A second derived
     * word must not quietly become the first.
     */
    public function test_resolving_the_singular_never_touches_the_nav_label(): void
    {
        $definition = $this->definition('scaffold-packs', 'Scaffold packs');

        $definition->resolvedSingularLabel();

        $this->assertSame('Scaffold packs', $definition->nav->label);
    }

    public function test_the_copy_wither_carries_the_slot_and_can_overlay_it(): void
    {
        $base = $this->definition('media', 'Media', singularLabel: 'Media');

        $this->assertSame('Media', $base->withOverrides(policy: 'anything')->singularLabel);
        $this->assertSame('Datum', $base->withOverrides(singularLabel: 'Datum')->singularLabel);
    }

    public function test_the_manifest_block_carries_the_resolved_word_for_the_shells(): void
    {
        $block = (new ContextManifest)->forResource(
            ContactResourceData::class,
            null,
            'scaffold-packs',
            'frame',
            $this->definition('scaffold-packs', 'Scaffold packs')->resolvedSingularLabel(),
        );

        $this->assertSame('Scaffold pack', $block['singularLabel']);
    }

    /**
     * Zero migration: every pre-existing caller passes at most four arguments, and the block it
     * gets must render exactly as it did — which for a shell means falling back to the key.
     */
    public function test_an_unaware_caller_gets_an_empty_word_and_the_client_falls_back(): void
    {
        $block = (new ContextManifest)->forResource(ContactResourceData::class);

        $this->assertSame('', $block['singularLabel']);
        $this->assertSame('frame', $block['createAffordance'], 'the neighbouring slot is unmoved');
    }

    /**
     * Both directions of the hazard. A declared noun cannot suppress a create that is open, and it
     * cannot resurrect one that is closed — it is not consulted by either resolution.
     */
    public function test_the_word_neither_opens_nor_closes_a_create(): void
    {
        $named = $this->definition('media', 'Media', singularLabel: 'Media');
        $this->assertSame('frame', $named->resolvedCreateAffordance());

        $closed = new ResourceDefinition(
            key: 'media',
            model: SampleModel::class,
            data: ContactResourceData::class,
            creatable: false,
            query: null,
            editData: null,
            policy: null,
            form: 'bare',
            nav: new NavMetadata(label: 'Media'),
            singularLabel: 'Media',
        );
        $this->assertSame('host', $closed->resolvedCreateAffordance());
    }
}
