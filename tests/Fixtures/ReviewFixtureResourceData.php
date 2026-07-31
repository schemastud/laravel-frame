<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Spatie\LaravelData\Data;

/**
 * The read projection of a source-backed (union) frame resource: `source` selects a
 * UnionSource instead of a single Eloquent `model`. The resource DECLARATION lives with
 * the producer; here the union definition is registered directly in the test. A union is
 * read + delegated-act only (not creatable, no editData/query).
 */
class ReviewFixtureResourceData extends Data
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $source,
        public string $id,
        public array $payload = [],
    ) {}
}
