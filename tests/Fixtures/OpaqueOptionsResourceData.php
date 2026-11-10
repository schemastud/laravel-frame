<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Schemastud\Frame\Attributes\Column;
use Schemastud\Frame\Attributes\Widget;
use Schemastud\Frame\Attributes\WidgetIn;
use Spatie\LaravelData\Data;

/**
 * frame-foundation 23 amend: author option bags whose own keys start with `x-`. An options bag is the author's,
 * emitted verbatim (ticket 01), so a key inside it is the widget's and never a frame wire key. The emitted-key test
 * must not list or demand these.
 */
class OpaqueOptionsResourceData extends Data
{
    public function __construct(
        #[Widget('splicewire-enrich', options: ['tone' => 'warm', 'x-client-option' => true])]
        public string $prose = '',
        #[WidgetIn('edit', 'email-input', options: ['x-edit-option' => 'kept'])]
        #[Column('badge', options: ['x-column-option' => 'kept'])]
        public string $email = '',
    ) {}
}
