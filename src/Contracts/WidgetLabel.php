<?php

namespace Schemastud\Frame\Contracts;

use ReflectionProperty;

/**
 * The label a property's widget context carries: a list column's header (`#[Column(label:)]`), or any
 * `#[WidgetIn($context, label:)]` (localization ticket 10). The default implementation,
 * {@see \Schemastud\Frame\Registry\IdentityWidgetLabel}, returns the declared label unchanged, so with nothing bound the
 * projected widget contexts are byte-identical.
 *
 * A package above this one binds its own to translate the label: splicewire/laravel-beam binds one that walks its
 * localization ladder. Return null to emit no label, which is what a context with no declared label emits today, so
 * the client falls back to the property's title.
 */
interface WidgetLabel
{
    /** The label for `$property` in `$context`. `$declared` is the attribute's own `label:`, or null. */
    public function label(ReflectionProperty $property, string $context, ?string $declared): ?string;
}
