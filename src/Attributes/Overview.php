<?php

namespace Schemastud\Frame\Attributes;

use Attribute;

/**
 * Class-level sugar for the `overview` collection context —
 * `#[WidgetIn('overview', $widget, $options)]`. Placed on the record (class) because
 * its subject is the WHOLE COLLECTION expanded to a card with a body (a period total,
 * a recent list), which no single property can stand for.
 *
 * `overview` cascades from `summary` (`ContextManifest` emits `overview ← summary`):
 * an overview declared without a widget name inherits the summary binding and folds
 * its own options over the summary's, unless `inheritsBinding: false`. `$widget`
 * null keeps that cascade; `false` opts the resource out of `overview`.
 *
 *   #[Overview('figure-card', options: ['period' => 'month'])]
 *   #[Overview(inheritsBinding: false)]
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Overview extends WidgetIn
{
    /**
     * @param  string|false|null  $widget  the overview widget name; null inherits the summary binding (or the widget-default), false opts out
     * @param  array<string, mixed>|null  $options  the per-widget render options, emitted verbatim to the wire
     * @param  string|null  $label  optional label override for the overview card
     * @param  bool|string  $inheritsBinding  false stops the `overview ← summary` cascade for this resource
     */
    public function __construct(
        string|false|null $widget = null,
        ?array $options = null,
        ?string $label = null,
        bool|string $inheritsBinding = true,
    ) {
        parent::__construct('overview', $widget ?? true, $options, label: $label, inheritsBinding: $inheritsBinding);
    }
}
