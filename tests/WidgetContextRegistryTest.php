<?php

namespace Schemastud\Frame\Tests;

use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use ReflectionClass;
use Rushing\Popcorn\Registries\RegistryIndex;
use Schemastud\DataSchemas\Generators\JsonSchemaGenerator;
use Schemastud\Frame\Keywords;
use Schemastud\Frame\Registry\ContextGrain;
use Schemastud\Frame\Registry\ContextManifest;
use Schemastud\Frame\Registry\WidgetContext;
use Schemastud\Frame\Registry\WidgetContextProjector;
use Schemastud\Frame\Registry\WidgetContextRegistry;
use Schemastud\Frame\Tests\Fixtures\HostContextResourceData;
use Schemastud\Frame\Tests\Fixtures\HostRecordContextOnPropertyData;
use Schemastud\Frame\Tests\Fixtures\RowActionsResourceData;
use Schemastud\Frame\Tests\Fixtures\UnknownContextData;
use Schemastud\Frame\Vocabulary\WidgetVocabulary;
use Schemastud\Frame\Wire\EmittedWireKeys;

/**
 * frame-foundation 90: a render context is a `frame.contexts` registry entry (decision 86's ruling). Frame seeds its
 * seven; a package or host registers or supersedes one; the block's `known`/`inherits` and the projector's grain and
 * class-level checks read the registry through the projector 89 hands in.
 */
class WidgetContextRegistryTest extends TestCase
{
    private const SEVEN = ['edit', 'detail', 'list-column', 'list-item', 'row-cell', 'summary', 'overview'];

    private function contexts(): WidgetContextRegistry
    {
        return $this->app->make(WidgetContextRegistry::class);
    }

    /** The two host contexts HostContextResourceData declares. */
    private function registerHostContexts(WidgetContextRegistry $registry): void
    {
        $registry->register(new WidgetContext('board-card', ContextGrain::Record, fallback: ['list-item']), by: 'acme/boards');
        $registry->register(new WidgetContext('inline-note', ContextGrain::Property), by: 'acme/boards');
    }

    public function test_it_is_a_declared_registry_seeded_by_frame_with_seven_entries(): void
    {
        $registry = $this->contexts();

        $this->assertSame($registry, $this->app->make(WidgetContextRegistry::class), 'one per container');
        $this->assertSame('frame.contexts', $registry->unfiltered()->declaration()->root);
        $this->assertSame(WidgetContext::class, $registry->unfiltered()->declaration()->entryType);
        $this->assertCount(7, $registry->unfiltered()->keys());
        $this->assertSame(
            array_map(fn (string $name): string => 'frame.contexts.'.$name, self::SEVEN),
            array_map(strval(...), $registry->unfiltered()->keys()),
        );
        foreach (self::SEVEN as $name) {
            $this->assertSame(WidgetContextRegistry::FRAME, $registry->unfiltered()->registrantOf('frame.contexts.'.$name), $name);
        }

        // In the index popcorn:registries reads, with frame as its owner.
        $roots = array_map(strval(...), app(RegistryIndex::class)->keys());
        $this->assertContains('frame.contexts', $roots);
    }

    public function test_popcorn_registries_lists_the_root(): void
    {
        $this->contexts();
        $exit = Artisan::call('popcorn:registries', ['--json' => true]);
        $rows = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exit, Artisan::output());
        $row = collect($rows)->firstWhere('root', 'frame.contexts');
        $this->assertNotNull($row, Artisan::output());
        $this->assertSame(WidgetContext::class, $row['entryType']);
    }

    public function test_the_block_reads_known_and_inherits_from_the_registry_in_registration_order(): void
    {
        $block = (new ContextManifest)->forResource(RowActionsResourceData::class);
        $this->assertSame(self::SEVEN, $block['known']);
        $this->assertSame(['row-cell' => ['edit'], 'overview' => ['summary']], $block['inherits']);

        // A host context lands at the end; it appears in inherits only because its fallback is non-empty.
        $this->registerHostContexts($this->contexts());
        $block = (new ContextManifest)->forResource(RowActionsResourceData::class);
        $this->assertSame([...self::SEVEN, 'board-card', 'inline-note'], $block['known']);
        $this->assertSame(['row-cell' => ['edit'], 'overview' => ['summary'], 'board-card' => ['list-item']], $block['inherits']);
    }

    public function test_a_supersession_keeps_its_slot_and_its_new_fallback(): void
    {
        $this->contexts()->register(new WidgetContext('detail', ContextGrain::Property, fallback: ['edit']), by: 'acme/detail');

        $block = (new ContextManifest)->forResource(RowActionsResourceData::class);
        $this->assertSame(self::SEVEN, $block['known']);
        $this->assertSame(['detail' => ['edit'], 'row-cell' => ['edit'], 'overview' => ['summary']], $block['inherits']);
        $this->assertSame('acme/detail', $this->contexts()->unfiltered()->registrantOf('frame.contexts.detail'));
    }

    public function test_registration_refuses_bad_entries_and_exposes_no_forget(): void
    {
        $refusals = [
            'not one segment' => [new WidgetContext('board.card', ContextGrain::Record), 'not one registry key segment'],
            'upper case' => [new WidgetContext('BoardCard', ContextGrain::Record), 'not one registry key segment'],
            'self fallback' => [new WidgetContext('board-card', ContextGrain::Record, fallback: ['board-card']), 'cannot fall back to itself'],
            'edit fallback' => [new WidgetContext('edit', ContextGrain::Property, fallback: ['detail']), '[edit] takes no fallback'],
            'reserved on record' => [new WidgetContext('board-card', ContextGrain::Record, reserved: ['x']), 'only a property-grain context reserves'],
        ];
        foreach ($refusals as $case => [$context, $message]) {
            try {
                $this->contexts()->register($context, by: 'acme/x');
                $this->fail("{$case}: no refusal");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $case);
            }
        }

        try {
            $this->contexts()->register(new WidgetContext('summary', ContextGrain::Record), by: 'acme/cards');
            $this->fail('grain change: no refusal');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('registered by schemastud/laravel-frame', $e->getMessage());
            $this->assertStringContainsString('acme/cards cannot supersede it', $e->getMessage());
        }

        // Nothing refused was written, and there is no way to remove a context.
        $this->assertSame(self::SEVEN, $this->contexts()->known());
        $this->assertFalse(method_exists(WidgetContextRegistry::class, 'forget'));
        $this->assertFalse(method_exists(WidgetContextRegistry::class, 'forgetBy'));
    }

    public function test_the_edges_check_names_the_missing_context_and_ignores_registration_order(): void
    {
        $missing = new WidgetContextRegistry;
        $missing->register(new WidgetContext('board-card', ContextGrain::Record, fallback: ['tile']), by: 'acme/a');
        try {
            $missing->assertEdges();
            $this->fail('missing fallback: no refusal');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('falls back to [tile], which is not registered', $e->getMessage());
            $this->assertStringContainsString('registered contexts: '.implode(', ', [...self::SEVEN, 'board-card']), $e->getMessage());
        }

        $grain = new WidgetContextRegistry;
        $grain->register(new WidgetContext('board-card', ContextGrain::Record, fallback: ['summary']), by: 'acme/a');
        $this->expectExceptionMessageMatches('/\(record grain\) falls back to \[summary\] \(collection grain\)/');
        $grain->assertEdges();
    }

    public function test_two_packages_registered_in_either_order_give_the_same_answer(): void
    {
        $a = fn (WidgetContextRegistry $r) => $r->register(new WidgetContext('board-card', ContextGrain::Record, fallback: ['tile']), by: 'acme/a');
        $b = fn (WidgetContextRegistry $r) => $r->register(new WidgetContext('tile', ContextGrain::Record, fallback: ['list-item']), by: 'acme/b');

        $ab = new WidgetContextRegistry;
        $a($ab);
        $b($ab);
        $ba = new WidgetContextRegistry;
        $b($ba);
        $a($ba);

        $ab->assertEdges();
        $ba->assertEdges();
        $this->assertEqualsCanonicalizing($ab->inherits(), $ba->inherits());
        $this->assertEqualsCanonicalizing($ab->known(), $ba->known());
    }

    public function test_frames_provider_checks_the_edges_once_after_boot(): void
    {
        $registry = $this->contexts();
        $registry->register(new WidgetContext('board-card', ContextGrain::Record, fallback: ['tile']), by: 'acme/a');

        // The provider's booted() pass is what runs this in an app; re-fire the app's booted callbacks.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('falls back to [tile], which is not registered');
        (fn () => $this->fireAppCallbacks($this->bootedCallbacks))->call($this->app);
    }

    public function test_a_projector_with_no_registry_checks_its_own_edges_on_first_read(): void
    {
        $projector = new WidgetContextProjector;
        $this->assertSame(self::SEVEN, $projector->known());
    }

    public function test_a_record_grain_host_context_falling_back_to_list_item_projects_at_class_level(): void
    {
        $this->registerHostContexts($this->contexts());
        $block = (new ContextManifest)->forResource(HostContextResourceData::class);

        $this->assertContains('board-card', $block['known']);
        $this->assertSame(['list-item'], $block['inherits']['board-card']);
        $this->assertSame(
            ['participates' => true, 'widget' => 'board-tile', 'options' => ['tone' => 'calm']],
            $block['byNode']['']['board-card'],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Widget context [board-card] is whole-record (class-level) only; it cannot be declared on a property.');
        (new ContextManifest)->forResource(HostRecordContextOnPropertyData::class);
    }

    public function test_a_host_property_grain_context_projects_through_the_block_and_the_schema_strategy(): void
    {
        $this->registerHostContexts($this->contexts());

        $block = (new ContextManifest)->forResource(HostContextResourceData::class);
        $this->assertSame(['participates' => true, 'widget' => 'note-chip'], $block['byNode']['title']['inline-note']);

        $schema = (new JsonSchemaGenerator)->forRequest()->generate(new ReflectionClass(HostContextResourceData::class));
        $this->assertSame(['participates' => true, 'widget' => 'note-chip'], $schema['properties']['title'][Keywords::WidgetContexts]['inline-note']);
    }

    public function test_an_undeclared_context_names_the_registered_contexts(): void
    {
        $this->registerHostContexts($this->contexts());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown widget context [bogus]; expected one of: '.implode(', ', [...self::SEVEN, 'board-card', 'inline-note']).'.');
        (new ContextManifest)->forResource(UnknownContextData::class);
    }

    public function test_row_actions_is_admitted_by_list_columns_reserved_names_not_a_string_comparison(): void
    {
        $block = (new ContextManifest)->forResource(RowActionsResourceData::class);
        $this->assertSame('row-actions', $block['byNode']['']['list-column']['widget']);

        $source = (string) file_get_contents((new ReflectionClass(WidgetContextProjector::class))->getFileName());
        $this->assertStringNotContainsString("'row-actions'", $source);
        $this->assertStringNotContainsString("'list-column'", $source);

        // Supersede list-column with no reserved names: the class-level #[RowActions] is refused.
        $this->contexts()->register(new WidgetContext('list-column', ContextGrain::Property), by: 'acme/columns');
        $this->expectExceptionMessage('Widget context [list-column] is per-property; it cannot be declared at the class level.');
        (new ContextManifest)->forResource(RowActionsResourceData::class);
    }

    public function test_the_three_context_constants_are_gone(): void
    {
        foreach (['KnownContexts', 'RecordContexts', 'CollectionContexts'] as $constant) {
            $this->assertFalse(defined(WidgetContextProjector::class.'::'.$constant), $constant);
        }
    }

    public function test_known_and_inherits_stay_unread_and_their_reason_names_the_registry(): void
    {
        $block = EmittedWireKeys::all()[EmittedWireKeys::GroupContextBlock];
        foreach (['known', 'inherits'] as $key) {
            $this->assertSame(EmittedWireKeys::ReaderUnread, $block[$key]['reader'], $key);
            $this->assertStringContainsString("frame's context registry (frame.contexts)", $block[$key]['reason'], $key);
            $this->assertStringNotContainsString('INHERITS', $block[$key]['reason'], $key);
        }
    }

    public function test_the_vocabulary_reads_inherits_or_frames_two_edges_when_absent(): void
    {
        $dir = sys_get_temp_dir().'/ff90-vocab-'.uniqid();
        mkdir($dir);
        file_put_contents("{$dir}/absent.json", json_encode(['version' => 1, 'contexts' => self::SEVEN]));
        file_put_contents("{$dir}/present.json", json_encode(['version' => 1, 'inherits' => ['row-cell' => ['edit', 'detail'], 'board-card' => ['list-item']]]));

        $this->assertSame(['row-cell' => ['edit'], 'overview' => ['summary']], WidgetVocabulary::fromFile("{$dir}/absent.json")->inherits());
        $this->assertSame(['row-cell' => ['edit', 'detail'], 'board-card' => ['list-item']], WidgetVocabulary::fromFile("{$dir}/present.json")->inherits());
    }
}
