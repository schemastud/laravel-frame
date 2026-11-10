<?php

namespace Schemastud\Frame\Tests;

use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\BadClassContextData;
use Schemastud\Frame\Tests\Fixtures\ColumnKindResourceData;
use Schemastud\Frame\Tests\Fixtures\ContactResourceData;
use Schemastud\Frame\Tests\Fixtures\SampleModel;
use Schemastud\Frame\Vocabulary\DeclaredWidget;
use Schemastud\Frame\Vocabulary\DeclaredWidgets;
use Schemastud\Frame\Vocabulary\VocabularyState;
use Schemastud\Frame\Vocabulary\WidgetVocabulary;

/**
 * frame-foundation 22 (ruling 05, build slice 2): PHP answers "is this name bound here" from the host's vocabulary
 * file, and lists what a resource declares, with no doctor dependency. One test over the package's fixture classes.
 */
class VocabularyAndDeclaredWidgetsTest extends TestCase
{
    public function test_column_kinds_are_yielded_under_list_column(): void
    {
        $walk = app(DeclaredWidgets::class)->walk($this->definition('kinds', ColumnKindResourceData::class));

        $this->assertSame([], $walk->failures);
        $this->assertSame(
            ['count' => 'number', 'declaredAt' => 'date', 'kind' => 'badge'],
            $this->names($walk->widgets, 'list-column'),
        );
        $kind = $this->only($walk->widgets, 'list-column', 'kind');
        $this->assertSame('kinds', $kind->resource);
        $this->assertSame(DeclaredWidget::SourceContextBlock, $kind->source);
    }

    public function test_an_ordinary_edit_entry_is_yielded_under_edit(): void
    {
        $walk = app(DeclaredWidgets::class)->walk($this->definition('contact', ContactResourceData::class));

        $this->assertSame([], $walk->failures);
        // From the served write schema (08 projects the ordinary entry onto x-widget) and from the context block.
        $write = $this->only($walk->widgets, 'edit', '/properties/email', DeclaredWidget::SourceWriteSchema);
        $this->assertSame('email-input', $write->name);
        $this->assertSame('string', $write->nodeType);
        $block = $this->only($walk->widgets, 'edit', 'email', DeclaredWidget::SourceContextBlock);
        $this->assertSame('email-input', $block->name);
        $this->assertFalse($block->heavyweight);
        // The heavyweight entry is declared in the block only, marked, and never reaches the write schema.
        $graph = $this->only($walk->widgets, 'edit', 'graph', DeclaredWidget::SourceContextBlock);
        $this->assertSame('circuit-graph', $graph->name);
        $this->assertTrue($graph->heavyweight);
        $this->assertSame([], array_values(array_filter($walk->widgets, fn (DeclaredWidget $w) => $w->pointer === '/properties/graph')));
    }

    public function test_a_wrong_grain_context_is_yielded_as_a_projection_failure_and_does_not_throw(): void
    {
        $walk = app(DeclaredWidgets::class)->walk($this->definition('bad', BadClassContextData::class));

        $this->assertCount(1, $walk->failures);
        $failure = $walk->failures[0];
        $this->assertSame('bad', $failure->resource);
        $this->assertSame(DeclaredWidget::SourceContextBlock, $failure->source);
        $this->assertSame(BadClassContextData::class, $failure->class);
        $this->assertStringContainsString('[edit] is per-property', $failure->message);
        $this->assertSame(\InvalidArgumentException::class, $failure->exception);
    }

    public function test_a_missing_file_reads_as_missing(): void
    {
        config()->set('frame.vocabulary', __DIR__.'/Fixtures/vocabulary/absent.json');

        $vocabulary = WidgetVocabulary::fromConfig();

        $this->assertSame(VocabularyState::Missing, $vocabulary->state);
        $this->assertSame(__DIR__.'/Fixtures/vocabulary/absent.json', $vocabulary->path);
        $this->assertFalse($vocabulary->isReadable());
        $this->assertFalse($vocabulary->binding('textarea', 'edit')->bound);
        $this->assertSame([], $vocabulary->contexts());
        $this->assertNull($vocabulary->wireKeys());
    }

    /** The file's `reads`: absent is null (inconclusive), present-and-empty is [], present is the list (22 §Interface decision, edits 1 and 4). */
    public function test_wire_keys_tell_an_absent_reads_from_an_empty_one(): void
    {
        $this->assertNull(WidgetVocabulary::fromFile(__DIR__.'/Fixtures/vocabulary/two-kits.json')->wireKeys());
        $this->assertSame([], WidgetVocabulary::fromFile(__DIR__.'/Fixtures/vocabulary/reads-empty.json')->wireKeys());
        $this->assertSame(['x-widget', 'byNode'], WidgetVocabulary::fromFile(__DIR__.'/Fixtures/vocabulary/reads-populated.json')->wireKeys());
    }

    public function test_a_conflicts_entry_answers_bound_and_no_node_types_for_that_context(): void
    {
        config()->set('frame.vocabulary', __DIR__.'/Fixtures/vocabulary/two-kits.json');

        $vocabulary = WidgetVocabulary::fromConfig();
        $this->assertSame(VocabularyState::Readable, $vocabulary->state);
        $this->assertSame(WidgetVocabulary::Version, $vocabulary->version());

        $conflicted = $vocabulary->binding('date', 'edit');
        $this->assertTrue($conflicted->bound);
        $this->assertTrue($conflicted->conflicted);
        $this->assertNull($conflicted->nodeTypes);

        // Outside the conflicted context the ordinary rule answers: the scoped list-column registration wins there.
        $column = $vocabulary->binding('date', 'list-column');
        $this->assertTrue($column->bound);
        $this->assertFalse($column->conflicted);
        $this->assertNull($column->nodeTypes);
        $this->assertSame(['string'], $vocabulary->binding('textarea', 'edit')->nodeTypes);
        $this->assertFalse($vocabulary->binding('badge', 'edit')->bound);
        $this->assertFalse($vocabulary->binding('bagde', 'list-column')->bound);
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
            nav: new NavMetadata(label: ucfirst($key)),
        );
    }

    /**
     * @param  list<DeclaredWidget>  $widgets
     * @return array<string, string> pointer => name, sorted by pointer
     */
    private function names(array $widgets, string $context): array
    {
        $names = [];
        foreach ($widgets as $widget) {
            if ($widget->context === $context) {
                $names[$widget->pointer] = $widget->name;
            }
        }
        ksort($names);

        return $names;
    }

    /** @param  list<DeclaredWidget>  $widgets */
    private function only(array $widgets, string $context, string $pointer, ?string $source = null): DeclaredWidget
    {
        $hits = array_values(array_filter($widgets, fn (DeclaredWidget $w) => $w->context === $context && $w->pointer === $pointer && ($source === null || $w->source === $source)));
        $this->assertCount(1, $hits, "one declared widget at {$context} {$pointer}");

        return $hits[0];
    }
}
