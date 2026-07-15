<?php

namespace Schemastud\Frame\Attributes;

use Attribute;
use Schemastud\Frame\Strategies\WidgetAttributesStrategy;

/**
 * Selects the form control for an edit-DTO property — projected to the
 * `x-stud-widget` JSON-Schema vendor keyword by {@see WidgetAttributesStrategy},
 * embedded in the one `forRequest()` schema (the #[Filterable]->x-filter way), NOT
 * a sidecar uiSchema. The value domain is an OPEN string — any key a host has
 * registered in seam's WidgetRegistry (`textarea`, `splicewire-enrich`, …); an
 * unknown key falls back gracefully to the inferred control on the frontend.
 *
 *   #[Widget('textarea')]
 *   #[Widget('splicewire-enrich')]
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Widget
{
    /**
     * @param  string  $name  a WidgetRegistry key ('textarea', 'splicewire-enrich', …)
     * @param  array<string, mixed>|null  $options  optional widget options (x-stud-widget-options)
     */
    public function __construct(
        public string $name,
        public ?array $options = null,
    ) {}
}
