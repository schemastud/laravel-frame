<?php

namespace Schemastud\Frame\Contracts;

/**
 * The request a host/frame builds to page a union source. A plain value object —
 * frame does NOT interpret `filters`; the UnionSource owns its own query semantics
 * (the `source` facet, date ranges, …). `cursor` rides the CursorPaginator contract.
 */
class UnionQuery
{
    /**
     * @param  array<string, mixed>  $filters  opaque bag the service interprets (incl. the `source` facet)
     */
    public function __construct(
        public array $filters = [],
        public ?string $cursor = null,
        public int $perPage = 25,
    ) {}
}
