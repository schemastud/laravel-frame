<?php

namespace Schemastud\Frame\Contracts;

use Spatie\LaravelData\Data;

/**
 * One resolved union item — the resource's envelope Data plus the (nullable) schema
 * `$ref` its payload resolves through. `schemaRef` is NULLABLE by design: a source's
 * payloads may have no published contract, so it MAY legitimately have no ref. When
 * null, the host falls back to a derived schema. `item` is type-hinted to the spatie
 * `Data` base so any envelope (the host's ReviewItem, a fixture) satisfies it.
 */
class ResolvedUnionItem extends Data
{
    public function __construct(
        public Data $item,
        public ?string $schemaRef = null,
    ) {}
}
