<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Schemastud\Frame\Attributes\Column;
use Spatie\LaravelData\Data;

/**
 * Exercises `#[Column]`'s presentation channel: the kind (its `$widget`, which has always
 * projected and until now nothing on the client read) plus the per-kind `$options` bag,
 * including the case where `$options` shares the bag with the `$filterable` sugar.
 */
class ColumnKindResourceData extends Data
{
    public function __construct(
        #[Column('badge', label: 'Kind', sort: 0, options: ['variant' => 'secondary'])]
        public string $kind = '',
        #[Column('date', label: 'Declared', sort: 1)]
        public ?string $declaredAt = null,
        // `filterable` is a FILTER fact and the kind options are a RENDER fact; they share
        // one bag on the wire and are not one declaration.
        #[Column('number', label: 'Count', sort: 2, filterable: true, options: ['zeroAsDash' => true])]
        public int $count = 0,
        // No kind — the pre-existing shape, which must stay byte-identical.
        #[Column(label: 'Plain', sort: 3)]
        public string $plain = '',
    ) {}
}
