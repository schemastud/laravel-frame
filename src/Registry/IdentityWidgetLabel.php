<?php

namespace Schemastud\Frame\Registry;

use ReflectionProperty;
use Schemastud\Frame\Contracts\WidgetLabel;

/**
 * The default {@see WidgetLabel}: the declared label, unchanged. With it bound (or nothing bound), widget contexts project
 * exactly as before the hook existed.
 */
final class IdentityWidgetLabel implements WidgetLabel
{
    public function label(ReflectionProperty $property, string $context, ?string $declared): ?string
    {
        return $declared;
    }

    /** The bound {@see WidgetLabel}, or this identity default where nothing is bound (or no container exists). */
    public static function current(): WidgetLabel
    {
        if (function_exists('app')) {
            try {
                if (app()->bound(WidgetLabel::class)) {
                    $label = app(WidgetLabel::class);
                    if ($label instanceof WidgetLabel) {
                        return $label;
                    }
                }
            } catch (\Throwable) {
                // No application: the identity default.
            }
        }

        return new self;
    }
}
