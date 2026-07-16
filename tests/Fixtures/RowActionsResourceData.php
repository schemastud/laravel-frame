<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Schemastud\Frame\Attributes\Column;
use Schemastud\Frame\Attributes\RowActions;
use Spatie\LaravelData\Data;

/**
 * Exercises the class-level #[RowActions] sugar — a `list-column` binding of the
 * `row-actions` widget placed on the record (the row-actions column is not backed by
 * any single property). The one class-level `list-column` case the projector permits.
 */
#[RowActions(['publish', 'archive'])]
class RowActionsResourceData extends Data
{
    public function __construct(
        #[Column(label: 'Title')]
        public string $title = '',
    ) {}
}
