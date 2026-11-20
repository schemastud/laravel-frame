<?php

namespace Schemastud\Frame\Registry;

use ReflectionProperty;
use Schemastud\Frame\Contracts\WidgetLabel;

/**
 * The default {@see WidgetLabel}: the declared label, unchanged. With it bound (or nothing bound), widget contexts project
 * exactly as before the hook existed. It is also what a {@see WidgetContextProjector} built with no hook uses.
 *
 * frame-foundation 89 removed its static `current()` locator: the hook is handed to the projector by constructor
 * (frame's provider builds the bound projector with the container's {@see WidgetLabel}), never located from inside it.
 */
final class IdentityWidgetLabel implements WidgetLabel
{
    public function label(ReflectionProperty $property, string $context, ?string $declared): ?string
    {
        return $declared;
    }
}
