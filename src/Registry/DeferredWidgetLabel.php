<?php

namespace Schemastud\Frame\Registry;

use Illuminate\Contracts\Container\Container;
use ReflectionProperty;
use Schemastud\Frame\Contracts\WidgetLabel;

/**
 * The container's {@see WidgetLabel}, resolved on the FIRST label() call, not when the holder is built
 * (frame-foundation 89, review MED 1).
 *
 * Frame's provider hands this to the bound {@see WidgetContextProjector}. A consumer that only asks WHICH contexts a
 * property declares (beam's contribution registry, at every boot) then builds no label chain at all. A host's hook (beam's
 * BeamWidgetLabel, whose construction reads the installed packages) is made exactly as before the hook was handed in: when
 * a label is first projected. Once resolved, the same instance answers every later call of this holder.
 */
final class DeferredWidgetLabel implements WidgetLabel
{
    private ?WidgetLabel $resolved = null;

    public function __construct(
        private readonly Container $container,
    ) {}

    public function label(ReflectionProperty $property, string $context, ?string $declared): ?string
    {
        return $this->resolve()->label($property, $context, $declared);
    }

    /**
     * The container's bound WidgetLabel, or frame's identity default where nothing is bound (a host or test that unbinds
     * it): the same answer the removed IdentityWidgetLabel::current() locator gave, so an unbound hook still projects
     * byte-identically instead of throwing (WidgetLabelHookTest).
     */
    public function resolve(): WidgetLabel
    {
        return $this->resolved ??= $this->container->bound(WidgetLabel::class)
            ? $this->container->make(WidgetLabel::class)
            : new IdentityWidgetLabel;
    }
}
