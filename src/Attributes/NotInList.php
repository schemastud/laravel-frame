<?php

namespace Schemastud\Frame\Attributes;

use Attribute;

/**
 * Sugar for `#[WidgetIn('list-column', bind: false)]` — the property opts OUT of the
 * list-column context (projected as `participates: false`), while still being free
 * to appear in `edit`/`detail`.
 *
 *   #[NotInList]
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class NotInList extends WidgetIn
{
    public function __construct()
    {
        parent::__construct('list-column', false);
    }
}
