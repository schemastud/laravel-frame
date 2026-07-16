<?php

namespace Schemastud\Frame\Attributes;

use Attribute;

/**
 * Sugar for `#[WidgetIn('list-column', …)]` — the common "show this property as a
 * list column" case spelled shorter. `$widget` null keeps the widget-default;
 * `$filterable` folds into the authoring options bag as `['filterable' => true]`.
 *
 *   #[Column]
 *   #[Column('badge', label: 'Status', sort: 2, filterable: true)]
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Column extends WidgetIn
{
    public function __construct(
        ?string $widget = null,
        ?string $label = null,
        ?int $sort = null,
        bool $filterable = false,
    ) {
        parent::__construct(
            'list-column',
            $widget ?? true,
            $filterable ? ['filterable' => true] : null,
            $sort,
            $label,
        );
    }
}
