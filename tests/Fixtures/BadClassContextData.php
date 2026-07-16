<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Schemastud\Frame\Attributes\WidgetIn;
use Spatie\LaravelData\Data;

/**
 * Arity violation fixture: a per-property context (`edit`) declared at the CLASS
 * level — must throw when projected.
 */
#[WidgetIn('edit', 'x')]
class BadClassContextData extends Data
{
    public function __construct(
        public string $field = '',
    ) {}
}
