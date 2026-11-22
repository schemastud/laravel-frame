<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Schemastud\Frame\Attributes\WidgetIn;
use Spatie\LaravelData\Data;

/** frame-foundation 90: a record-grain host context declared on a PROPERTY, which the projector refuses. */
class HostRecordContextOnPropertyData extends Data
{
    public function __construct(
        #[WidgetIn('board-card', 'board-tile')]
        public string $title = '',
    ) {}
}
