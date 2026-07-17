<?php

namespace Schemastud\Frame\Tests;

use ReflectionClass;
use Schemastud\DataSchemas\Generators\JsonSchemaGenerator;
use Schemastud\DataSchemas\Overlay\OverlayStack;
use Schemastud\Frame\Keywords;
use Schemastud\Frame\Tests\Fixtures\ContactResourceData;

/**
 * DO-07 — the widget-context projection now rides the DataOverlay primitive
 * (SchemaOverlayApplier), byte-for-byte. This pins the convergence and exercises
 * the options-cascade deep-merge SHAPE through DataOverlay `merge`.
 */
class WidgetContextsConvergenceTest extends TestCase
{
    private function requestSchema(): array
    {
        return (new JsonSchemaGenerator)
            ->forRequest()
            ->generate(new ReflectionClass(ContactResourceData::class));
    }

    public function test_projection_through_the_overlay_fold_is_byte_for_byte(): void
    {
        // Same assertion the pre-convergence strategy pinned — now produced by
        // SchemaOverlayApplier folding an `override` overlay, not a hand assignment.
        $email = $this->requestSchema()['properties']['email'][Keywords::WidgetContexts];

        $this->assertSame([
            'edit' => [
                'participates' => true,
                'widget' => 'email-input',
                'options' => ['autocomplete' => 'email'],
            ],
            'list-column' => [
                'participates' => true,
                'widget' => 'email-cell',
                'options' => ['filterable' => true],
                'sort' => 1,
                'label' => 'Email',
            ],
            'row-cell' => [
                'participates' => true,
            ],
        ], $email);
    }

    public function test_options_cascade_deep_merge_is_dataoverlay_merge(): void
    {
        // The options cascade — a context bag folded over a base bag — is a CLIENT
        // concern (resolveWidgetFor), outside DataOverlay in production. But when it
        // IS folded, it is DataOverlay `merge`: a deep-merge that folds the context
        // options over the base (overwriting overlaps, keeping the rest), NOT the
        // wholesale replace the server strategy emits with `override`.
        $base = ['options' => ['autocomplete' => 'email', 'clearable' => true]];

        $folded = (new OverlayStack([[
            'overlay' => '1.0.0',
            'actions' => [
                ['target' => '$.options', 'merge' => ['clearable' => false, 'dense' => true]],
            ],
        ]]))->apply($base);

        $this->assertSame(
            ['options' => ['autocomplete' => 'email', 'clearable' => false, 'dense' => true]],
            $folded,
        );
    }
}
