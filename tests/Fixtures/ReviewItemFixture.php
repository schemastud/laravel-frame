<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Spatie\LaravelData\Data;

/**
 * A representative union envelope, standing in for the host's real (out-of-scope)
 * ReviewItem. `source` is a CLOSED discriminator ('alpha'|'beta') — the spine every
 * consumer reads verbatim; `payload` is an opaque schemaless bag.
 */
class ReviewItemFixture extends Data
{
    /**
     * @param  'alpha'|'beta'  $source
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $source,
        public string $id,
        public array $payload = [],
    ) {}
}
