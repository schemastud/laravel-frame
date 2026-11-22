<?php

namespace Schemastud\Frame\Tests;

use ReflectionProperty;
use Schemastud\Frame\Contracts\WidgetLabel;
use Schemastud\Frame\Registry\ContextManifest;
use Schemastud\Frame\Registry\DeferredWidgetLabel;
use Schemastud\Frame\Registry\IdentityWidgetLabel;
use Schemastud\Frame\Registry\WidgetContextProjector;
use Schemastud\Frame\Strategies\WidgetContextsStrategy;
use Schemastud\Frame\Tests\Fixtures\ColumnKindResourceData;

/**
 * frame-foundation 89 — the context projector is handed in, never constructed in place.
 *
 * The second, differently-shaped probe beside the source grep: it asks the CONTAINER what a consumer actually holds,
 * so a consumer that quietly built its own projector fails here even if no `new` reads like one. Ticket 90 then changes
 * the projector in one place (frame's provider binding) and every consumer reads it.
 */
class ProjectorHandedInTest extends TestCase
{
    private function held(object $consumer): WidgetContextProjector
    {
        return (new ReflectionProperty($consumer, 'projector'))->getValue($consumer);
    }

    private function hook(): WidgetLabel
    {
        return new class implements WidgetLabel
        {
            public function label(ReflectionProperty $property, string $context, ?string $declared): ?string
            {
                return $declared === null ? null : "<{$declared}>";
            }
        };
    }

    public function test_the_container_resolved_manifest_and_strategy_hold_the_bound_projector(): void
    {
        $bound = new WidgetContextProjector;
        $this->app->instance(WidgetContextProjector::class, $bound);

        $this->assertSame($bound, $this->held($this->app->make(ContextManifest::class)));
        $this->assertSame($bound, $this->held($this->app->make(WidgetContextsStrategy::class)));
    }

    public function test_the_bound_projector_is_built_with_the_containers_label_hook(): void
    {
        $hook = $this->hook();
        $this->app->instance(WidgetLabel::class, $hook);

        $projector = $this->app->make(WidgetContextProjector::class);

        // review MED 1: the projector holds a DEFERRED handle that resolves to the container's hook
        $labels = (new ReflectionProperty($projector, 'labels'))->getValue($projector);
        $this->assertInstanceOf(DeferredWidgetLabel::class, $labels);
        $this->assertSame($hook, $labels->resolve());
        $this->assertSame('<Kind>', $this->app->make(ContextManifest::class)->forResource(ColumnKindResourceData::class)['byNode']['kind']['list-column']['label']);
    }

    public function test_building_the_bound_projector_makes_no_widget_label_until_a_label_is_projected(): void
    {
        // review MED 1: beam builds a projector at every boot for a check that reads no label; that must build no hook
        $made = 0;
        $this->app->bind(WidgetLabel::class, function () use (&$made): WidgetLabel {
            $made++;

            return $this->hook();
        });

        $projector = $this->app->make(WidgetContextProjector::class);
        $this->app->make(ContextManifest::class);
        $this->assertSame(0, $made, 'building the projector (or a manifest holding it) must not make a WidgetLabel');

        $property = new ReflectionProperty(ColumnKindResourceData::class, 'kind');
        $projector->forClass(new \ReflectionClass(ColumnKindResourceData::class));
        $this->assertSame(0, $made, 'class-level contexts read no label hook');

        $this->assertSame('<Kind>', $projector->forProperty($property)['list-column']['label']);
        $projector->forProperty($property);
        $this->assertSame(1, $made, 'the first property label resolves the hook, once per projector');
    }

    public function test_a_projector_uses_the_hook_it_was_handed_and_identity_without_one(): void
    {
        $property = new ReflectionProperty(ColumnKindResourceData::class, 'kind');

        $this->assertSame('<Kind>', (new WidgetContextProjector($this->hook()))->forProperty($property)['list-column']['label']);
        $this->assertSame('Kind', (new WidgetContextProjector(new IdentityWidgetLabel))->forProperty($property)['list-column']['label']);
    }

    public function test_resolved_is_the_containers_bound_projector_when_an_app_is_booted(): void
    {
        $bound = new WidgetContextProjector($this->hook());
        $this->app->instance(WidgetContextProjector::class, $bound);

        $this->assertSame($bound, WidgetContextProjector::resolved());
        // so a hand-built manifest inside the app reads what the container's reads (decision D/O1)
        $this->assertSame($bound, $this->held(new ContextManifest));
        $this->assertSame($bound, $this->held(new WidgetContextsStrategy));
    }

    public function test_resolved_is_a_plain_identity_projector_with_no_application(): void
    {
        $app = \Illuminate\Container\Container::getInstance();
        \Illuminate\Container\Container::setInstance(null); // the next getInstance() is a fresh, empty container

        try {
            $projector = WidgetContextProjector::resolved();
            $this->assertNull((new ReflectionProperty($projector, 'labels'))->getValue($projector));
            $this->assertSame('Kind', $projector->forProperty(new ReflectionProperty(ColumnKindResourceData::class, 'kind'))['list-column']['label']);
        } finally {
            \Illuminate\Container\Container::setInstance($app);
        }
    }

    public function test_the_label_hooks_static_locator_is_gone(): void
    {
        $this->assertFalse(method_exists(IdentityWidgetLabel::class, 'current'));
    }

    public function test_src_constructs_a_projector_only_in_the_provider_binding(): void
    {
        $sites = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__).'/src', \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            foreach (file($file->getPathname()) as $n => $line) {
                if (preg_match('/^\s*(\*|\/\/)/', $line) || ! preg_match('/new\s+\\\\?(?:[A-Za-z]+\\\\)*WidgetContextProjector\b/', $line)) {
                    continue;
                }
                // review LOW 3: the provider's binding is the ONLY allowed site (frame's nullable defaults call resolved());
                // a later `?? new WidgetContextProjector` anywhere in frame's src/ fails here
                // frame-foundation 90: the binding also hands the projector frame's context registry
                $allowed = $file->getFilename() === 'FrameServiceProvider.php' && str_contains($line, 'new WidgetContextProjector(new DeferredWidgetLabel($app), $app->make(WidgetContextRegistry::class))');
                if (! $allowed) {
                    $sites[] = $file->getFilename().':'.($n + 1).': '.trim($line);
                }
            }
        }

        $this->assertSame([], $sites, 'a projector constructed in place');
    }
}
