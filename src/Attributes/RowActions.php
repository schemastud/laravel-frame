<?php

namespace Schemastud\Frame\Attributes;

use Attribute;

/**
 * Class-level sugar for a `list-column` that renders the row's action controls —
 * `#[WidgetIn('list-column', 'row-actions', ['actions' => [...]])]`. Placed on the
 * record (class) rather than a property, since a row-actions column is not backed by
 * a single field.
 *
 *   #[RowActions(['edit', 'delete'])]
 */
#[Attribute(Attribute::TARGET_CLASS)]
class RowActions extends WidgetIn
{
    /**
     * @param  array<int, mixed>  $actions
     */
    public function __construct(public array $actions = [])
    {
        parent::__construct('list-column', 'row-actions', ['actions' => $actions]);
    }
}
