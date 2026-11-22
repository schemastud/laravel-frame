<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Schemastud\Frame\Attributes\WidgetIn;
use Spatie\LaravelData\Data;

/**
 * frame-foundation 90: a resource that declares two HOST render contexts, registered by the test: `board-card`, a
 * record-grain context falling back to frame's `list-item` (class level), and `inline-note`, a property-grain context
 * (on a property).
 */
#[WidgetIn('board-card', 'board-tile', options: ['tone' => 'calm'])]
class HostContextResourceData extends Data
{
    public function __construct(
        #[WidgetIn('inline-note', 'note-chip')]
        public string $title = '',
    ) {}
}
