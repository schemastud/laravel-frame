<?php

namespace Schemastud\Frame\Registry;

use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use Schemastud\Frame\Attributes\Widget;
use Schemastud\Frame\Attributes\WidgetIn;
use Schemastud\Frame\Contracts\WidgetLabel;
use Schemastud\Frame\Strategies\WidgetContextsStrategy;

/**
 * The single source of truth for turning `#[WidgetIn]`-family attributes into the
 * `{context => entry}` wire map — shared by {@see WidgetContextsStrategy}
 * (per-property, embedded in the property schema) and {@see ContextManifest} (per
 * resource node), so the two never diverge. Also owns the closed-enum + arity
 * validation, and the refusal of a property that declares its form control twice
 * (`#[Widget]` beside an ordinary edit entry), thrown as \InvalidArgumentException.
 *
 * Handed in, never located (frame-foundation 89): the label hook arrives by NULLABLE CONSTRUCTOR ARGUMENT, and null is
 * {@see IdentityWidgetLabel} (the declared label, unchanged), so `new WidgetContextProjector` is a plain projector with no
 * container. Every production consumer is GIVEN its projector: frame's provider binds the one built with the container's
 * {@see WidgetLabel} (beam's, where beam binds one), and {@see ContextManifest} and {@see WidgetContextsStrategy} take it
 * by constructor. Nothing in the family constructs one in place, so a projector that one day reads a registry is read
 * the same way everywhere.
 */
class WidgetContextProjector
{
    public function __construct(
        protected ?WidgetLabel $labels = null,
    ) {}

    /**
     * The ONE named edge where a consumer that was handed nothing obtains a projector (frame-foundation 89, decision
     * D/O1): whatever the current container (`app()`) resolves for this class, else a plain one (identity labels). It
     * does NOT check that the application is booted: once frame's provider has registered, that is the bound projector
     * (built with a deferred handle on the bound {@see WidgetLabel}); called earlier, or in a container frame never
     * registered in, the container autowires a plain projector. With no container at all it is a plain one. Used only by
     * the nullable defaults of {@see
     * ContextManifest} and {@see WidgetContextsStrategy}, so `new ContextManifest` inside an app reads exactly what the
     * container's manifest reads. The projector itself never locates anything: its label hook arrives by constructor.
     */
    public static function resolved(): self
    {
        if (function_exists('app')) {
            try {
                $projector = app(self::class);
                if ($projector instanceof self) {
                    return $projector;
                }
            } catch (\Throwable) {
                // No application: the plain projector below.
            }
        }

        return new self;
    }

    /**
     * The closed seven-context enum, spanning three subject grains: per-property
     * (`edit`, `detail`, `list-column`, `row-cell`), whole-record (`list-item`) and
     * whole-collection (`summary`, `overview`). See {@see WidgetIn} for the grains.
     */
    public const KnownContexts = ['edit', 'detail', 'list-column', 'list-item', 'row-cell', 'summary', 'overview'];

    /**
     * Contexts whose subject is ONE RECORD — class-level only, since no single
     * property can stand for the record.
     */
    public const RecordContexts = ['list-item'];

    /**
     * Contexts whose subject is the WHOLE COLLECTION — class-level only, for the same
     * reason one grain up: `summary` compresses the collection to figures, `overview`
     * expands it to a card with a body. The cascade `overview ← summary` is declared
     * by {@see ContextManifest}, not here.
     */
    public const CollectionContexts = ['summary', 'overview'];

    /**
     * Project a property's WidgetIn-family attributes to the `{context => entry}` map.
     * Strict no-op semantics: an empty attribute list yields an empty array.
     *
     * @return array<string, array<string, mixed>>
     */
    public function forProperty(ReflectionProperty $property): array
    {
        $map = $this->project(
            $property->getAttributes(WidgetIn::class, ReflectionAttribute::IS_INSTANCEOF),
            classLevel: false,
            property: $property,
        );

        // A property declares its form control once: `#[Widget]` or an ordinary edit entry, never both. Both are the
        // same keyword on the wire, so a second spelling could only disagree with the first.
        if (self::ordinaryEditWidget($map) !== null && $property->getAttributes(Widget::class) !== []) {
            throw new InvalidArgumentException(
                "Property [{$property->getDeclaringClass()->getName()}::\${$property->getName()}] declares its form control twice: "
                .'#[Widget] and an ordinary #[WidgetIn(\'edit\', …)]. Declare it once.'
            );
        }

        return $map;
    }

    /**
     * The form control an ordinary (not heavyweight) edit entry binds, read from a property's projected map: the
     * name and the entry's options, which {@see WidgetContextsStrategy} projects onto `x-widget` /
     * `x-widget-options`. Null when there is no edit entry, when it binds no name, or when it is heavyweight (a
     * heavyweight entry names a full-surface editor, not a form control).
     *
     * @param  array<string, array<string, mixed>>  $map
     * @return array{name: string, options: array<string, mixed>|null}|null
     */
    public static function ordinaryEditWidget(array $map): ?array
    {
        $edit = $map['edit'] ?? null;

        if ($edit === null || ! is_string($edit['widget'] ?? null) || ($edit['heavyweight'] ?? false) === true) {
            return null;
        }

        /** @var array<string, mixed>|null $options */
        $options = $edit['options'] ?? null;

        return ['name' => $edit['widget'], 'options' => $options];
    }

    /**
     * Project a class's WidgetIn-family attributes (list-item / summary / overview /
     * row-actions) to the `{context => entry}` map.
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
    private function project(array $attributes, bool $classLevel, ?ReflectionProperty $property = null): array
    {
        $map = [];

        foreach ($attributes as $attribute) {
            $widgetIn = $attribute->newInstance();

            $this->validate($widgetIn->context, $classLevel, $widgetIn->bind);

            $map[$widgetIn->context] = $this->entry($widgetIn, $property);
        }

        return $map;
    }

    /**
     * Build one per-context entry — participates flag + only the meaningfully-set
     * fields. Options are emitted UN-merged (verbatim from the authoring bag).
     *
     * @return array<string, mixed>
     */
    private function entry(WidgetIn $widgetIn, ?ReflectionProperty $property = null): array
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

        // localization 10: a property context's label goes through the label hook (identity by default, so unchanged);
        // a class-level context has no derived key and keeps its declared label. 89: the hook is this projector's own,
        // handed in by constructor (null = identity), never located.
        $label = $property === null
            ? $widgetIn->label
            : ($this->labels ?? new IdentityWidgetLabel)->label($property, $widgetIn->context, $widgetIn->label);

        if ($label !== null) {
            $entry['label'] = $label;
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

        // Record and collection contexts are both class-only: their subject is larger
        // than any one property.
        $collection = in_array($context, self::CollectionContexts, true);
        $classOnly = $collection || in_array($context, self::RecordContexts, true);

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
            $grain = $collection ? 'whole-collection' : 'whole-record';

            throw new InvalidArgumentException(
                "Widget context [{$context}] is {$grain} (class-level) only; it cannot be declared on a property."
            );
        }
    }
}
