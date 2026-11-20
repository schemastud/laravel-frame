<?php

namespace Schemastud\Frame\Tests;

use ReflectionProperty;
use Schemastud\Frame\Contracts\WidgetLabel;
use Schemastud\Frame\Registry\ContextManifest;
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

        $this->assertSame($hook, (new ReflectionProperty($projector, 'labels'))->getValue($projector));
        $this->assertSame('<Kind>', $this->app->make(ContextManifest::class)->forResource(ColumnKindResourceData::class)['byNode']['kind']['list-column']['label']);
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

    public function test_src_constructs_a_projector_only_in_the_provider_binding_or_a_nullable_default(): void
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
                $allowed = str_contains($line, '?? new WidgetContextProjector')
                    || ($file->getFilename() === 'FrameServiceProvider.php' && str_contains($line, '$app->make(WidgetLabel::class)'));
                if (! $allowed) {
                    $sites[] = $file->getFilename().':'.($n + 1).': '.trim($line);
                }
            }
        }

        $this->assertSame([], $sites, 'a projector constructed in place');
    }
}
