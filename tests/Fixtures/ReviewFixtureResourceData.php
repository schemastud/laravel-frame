<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Schemastud\Frame\Attributes\AdminResource;
use Spatie\LaravelData\Data;

/**
 * A source-backed (union) frame resource: `source` selects a UnionSource instead of
 * a single Eloquent `model`. Registration nulls out editData/query and marks it
 * not-creatable — a union is read + delegated-act only.
 */
#[AdminResource(
    key: 'review-fixture',
    label: 'Review',
    source: FixtureUnionSource::class,
    group: 'Workflow',
    icon: 'inbox',
    policy: 'review',
)]
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
