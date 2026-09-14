<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Schemastud\Frame\Attributes\Column;
use Schemastud\Frame\Attributes\Overview;
use Schemastud\Frame\Attributes\Summary;
use Spatie\LaravelData\Data;

/**
 * Exercises the two class-level COLLECTION contexts through their sugar: `#[Summary]`
 * (the collection compressed to figures) and `#[Overview]` (the collection expanded to
 * a card with a body). Both land at the manifest's root pointer beside `list-item`.
 */
#[Summary('stat-row')]
#[Overview('figure-card', options: ['period' => 'month', 'recent' => 5])]
class SummaryOverviewResourceData extends Data
{
    public function __construct(
        #[Column(label: 'Title')]
        public string $title = '',
    ) {}
}
