<?php

namespace Schemastud\Frame\Tests;

use ReflectionProperty;
use Schemastud\Frame\Contracts\WidgetLabel;
use Schemastud\Frame\Registry\ContextManifest;
use Schemastud\Frame\Registry\IdentityWidgetLabel;
use Schemastud\Frame\Tests\Fixtures\ColumnKindResourceData;

/**
 * localization 10 — the label hook where frame projects a property's widget contexts. Unbound (or bound to the identity
 * default) the projection is byte-identical; a bound hook is consulted for every property context, with the declared
 * label (or null) as its default; a context with no label stays label-less unless the hook supplies one.
 */
class WidgetLabelHookTest extends TestCase
{
    private function byNode(): array
    {
        return (new ContextManifest)->forResource(ColumnKindResourceData::class)['byNode'];
    }

    public function test_the_identity_default_is_bound_and_leaves_the_projection_unchanged(): void
    {
        $this->assertInstanceOf(IdentityWidgetLabel::class, $this->app->make(WidgetLabel::class));
        $bound = json_encode($this->byNode());

        $this->app->offsetUnset(WidgetLabel::class);
        $this->assertSame(json_encode($this->byNode()), $bound);
        $this->assertSame('Kind', $this->byNode()['kind']['list-column']['label']);
    }

    public function test_a_bound_hook_rewrites_every_property_context_label_and_may_supply_one(): void
    {
        $seen = [];
        $this->app->instance(WidgetLabel::class, new class($seen) implements WidgetLabel
        {
            public function __construct(private array &$seen) {}

            public function label(ReflectionProperty $property, string $context, ?string $declared): ?string
            {
                $this->seen[] = [$property->getName(), $context, $declared];

                return $declared === null ? null : "<{$declared}>";
            }
        });

        $byNode = $this->byNode();

        $this->assertSame('<Kind>', $byNode['kind']['list-column']['label']);
        $this->assertSame('<Plain>', $byNode['plain']['list-column']['label'] ?? null);
        $this->assertContains(['kind', 'list-column', 'Kind'], $seen);
    }
}
