<?php

namespace Schemastud\Frame\Attributes;

use Attribute;

/**
 * Class-level sugar for the `summary` collection context —
 * `#[WidgetIn('summary', $widget, $options)]`. Placed on the record (class) because
 * its subject is the WHOLE COLLECTION compressed to figures (a stat tile), which no
 * single property can stand for.
 *
 * `$widget` null keeps the widget-default; `false` opts the resource out of
 * participating in `summary` at all (the way `#[NotInList]` opts a property out of
 * `list-column`).
 *
 *   #[Summary]
 *   #[Summary('stat-row')]
 *   #[Summary(false)]
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Summary extends WidgetIn
{
    /**
     * @param  string|false|null  $widget  the summary widget name; null keeps the widget-default, false opts out
     * @param  array<string, mixed>|null  $options  the per-widget render options, emitted verbatim to the wire
     * @param  string|null  $label  optional label override for the summary card
     */
    public function __construct(
        string|false|null $widget = null,
        ?array $options = null,
        ?string $label = null,
    ) {
        parent::__construct('summary', $widget ?? true, $options, label: $label);
    }
}
