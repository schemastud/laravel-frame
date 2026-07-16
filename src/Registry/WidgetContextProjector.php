<?php

namespace Schemastud\Frame\Registry;

use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use Schemastud\Frame\Attributes\WidgetIn;
use Schemastud\Frame\Strategies\WidgetContextsStrategy;

/**
 * The single source of truth for turning `#[WidgetIn]`-family attributes into the
 * `{context => entry}` wire map — shared by {@see WidgetContextsStrategy}
 * (per-property, embedded in the property schema) and {@see ContextManifest} (per
 * resource node), so the two never diverge. Also owns the closed-enum + arity
 * validation, thrown as \InvalidArgumentException.
 */
class WidgetContextProjector
{
    /** The closed five-context enum. */
    public const KnownContexts = ['edit', 'detail', 'list-column', 'list-item', 'row-cell'];

    /** Contexts that are whole-record (class-level) only. */
    private const ClassOnlyContexts = ['list-item'];

    /**
     * Project a property's WidgetIn-family attributes to the `{context => entry}` map.
     * Strict no-op semantics: an empty attribute list yields an empty array.
     *
     * @return array<string, array<string, mixed>>
     */
    public function forProperty(ReflectionProperty $property): array
    {
        return $this->project(
            $property->getAttributes(WidgetIn::class, ReflectionAttribute::IS_INSTANCEOF),
            classLevel: false,
        );
    }

    /**
     * Project a class's WidgetIn-family attributes (list-item / row-actions) to the
     * `{context => entry}` map.
     *
     * @param  ReflectionClass<object>  $class
     * @return array<string, array<string, mixed>>
     */
    public function forClass(ReflectionClass $class): array
    {
        return $this->project(
            $class->getAttributes(WidgetIn::class, ReflectionAttribute::IS_INSTANCEOF),
            classLevel: true,
        );
    }

    /**
     * @param  array<int, ReflectionAttribute<WidgetIn>>  $attributes
     * @return array<string, array<string, mixed>>
     */
    private function project(array $attributes, bool $classLevel): array
    {
        $map = [];

        foreach ($attributes as $attribute) {
            $widgetIn = $attribute->newInstance();

            $this->validate($widgetIn->context, $classLevel, $widgetIn->bind);

            $map[$widgetIn->context] = $this->entry($widgetIn);
        }

        return $map;
    }

    /**
     * Build one per-context entry — participates flag + only the meaningfully-set
     * fields. Options are emitted UN-merged (verbatim from the authoring bag).
     *
     * @return array<string, mixed>
     */
    private function entry(WidgetIn $widgetIn): array
    {
        $entry = [];

        if ($widgetIn->bind === false) {
            $entry['participates'] = false;

            return $entry;
        }

        $entry['participates'] = true;

        // bind === true keeps the widget-default (no explicit widget); a string binds it.
        if (is_string($widgetIn->bind)) {
            $entry['widget'] = $widgetIn->bind;
        }

        if ($widgetIn->options !== null) {
            $entry['options'] = $widgetIn->options;
        }

        if ($widgetIn->sort !== null) {
            $entry['sort'] = $widgetIn->sort;
        }

        if ($widgetIn->label !== null) {
            $entry['label'] = $widgetIn->label;
        }

        // inheritsBinding defaults to true; emit only when explicitly narrowed.
        if ($widgetIn->inheritsBinding !== true) {
            $entry['inheritsBinding'] = $widgetIn->inheritsBinding;
        }

        if ($widgetIn->heavyweight) {
            $entry['heavyweight'] = true;
        }

        return $entry;
    }

    private function validate(string $context, bool $classLevel, string|bool $bind = true): void
    {
        if (! in_array($context, self::KnownContexts, true)) {
            throw new InvalidArgumentException(
                "Unknown widget context [{$context}]; expected one of: ".implode(', ', self::KnownContexts).'.'
            );
        }

        $classOnly = in_array($context, self::ClassOnlyContexts, true);

        // The class-level #[RowActions] sugar is a `list-column` binding of the `row-actions`
        // widget placed on the record (the row-actions column is not backed by any single
        // property). Permit that one class-level `list-column` case; every other per-property
        // context stays property-only at the class level.
        $classLevelRowActions = $context === 'list-column' && $bind === 'row-actions';

        if ($classLevel && ! $classOnly && ! $classLevelRowActions) {
            throw new InvalidArgumentException(
                "Widget context [{$context}] is per-property; it cannot be declared at the class level."
            );
        }

        if (! $classLevel && $classOnly) {
            throw new InvalidArgumentException(
                "Widget context [{$context}] is whole-record (class-level) only; it cannot be declared on a property."
            );
        }
    }
}
