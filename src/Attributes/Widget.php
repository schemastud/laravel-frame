<?php

namespace Schemastud\Frame\Attributes;

use Attribute;
use Schemastud\Frame\Strategies\WidgetAttributesStrategy;

/**
 * Selects the form control for an edit-DTO property — projected to the
 * `x-widget` JSON-Schema vendor keyword by {@see WidgetAttributesStrategy},
 * embedded in the one `forRequest()` schema (the #[Filterable]->x-filter way), NOT
 * a sidecar uiSchema. The value domain is an OPEN string — any key a host has
 * registered in seam's WidgetRegistry (`textarea`, `splicewire-enrich`, …); an
 * unknown key draws the unbound marker on the frontend and is reported by Beam's
 * advisory `frame.widget-name` check against the host vocabulary (docs/attributes.md).
 *
 * An ordinary (not heavyweight) `#[WidgetIn('edit', …)]` projects onto the same keyword,
 * so a property declares its form control with one or the other, never both: the
 * projector refuses a property carrying both (ADR-0006).
 *
 *   #[Widget('textarea')]
 *   #[Widget('splicewire-enrich')]
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Widget
{
    /**
     * @param  string  $name  a WidgetRegistry key ('textarea', 'splicewire-enrich', …)
     * @param  array<string, mixed>|null  $options  optional widget options (x-widget-options)
     */
    public function __construct(
        public string $name,
        public ?array $options = null,
    ) {}
}
