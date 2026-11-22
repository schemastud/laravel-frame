<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Schemastud\Frame\Attributes\WidgetIn;
use Spatie\LaravelData\Data;

/** frame-foundation 90: a class-level declaration of a context nobody registered. */
#[WidgetIn('bogus', 'x')]
class UnknownContextData extends Data {}
