<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Schemastud\Frame\Attributes\Column;
use Schemastud\Frame\Attributes\NotInList;
use Schemastud\Frame\Attributes\WidgetIn;
use Spatie\LaravelData\Data;

/**
 * Exercises the WidgetIn family: a multi-context property (edit + list-column via
 * #[Column] + row-cell inheriting binding), a #[NotInList] opt-out property, a
 * class-level list-item declaration, and a heavyweight edit widget.
 */
#[WidgetIn('list-item', 'contact-card')]
class ContactResourceData extends Data
{
    public function __construct(
        #[WidgetIn('edit', 'email-input', options: ['autocomplete' => 'email'])]
        #[Column('email-cell', label: 'Email', sort: 1, filterable: true)]
        #[WidgetIn('row-cell', inheritsBinding: true)]
        public string $email = '',
        #[NotInList]
        public string $secret = '',
        #[WidgetIn('edit', 'circuit-graph', heavyweight: true)]
        public string $graph = '',
    ) {}
}
