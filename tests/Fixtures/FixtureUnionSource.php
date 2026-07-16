<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Illuminate\Contracts\Pagination\CursorPaginator as CursorPaginatorContract;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Schemastud\Frame\Contracts\ResolvedUnionItem;
use Schemastud\Frame\Contracts\UnionQuery;
use Schemastud\Frame\Contracts\UnionSource;

/**
 * A representative UnionSource for the frame test suite — fuses two in-memory arms
 * ('alpha', 'beta') into one cursor-paginated list, mirroring what the host's real
 * ReviewInbox does over its two Eloquent arms. Proves the contract (merge + slice +
 * advancing cursor + find) without any host/DB dependency.
 */
class FixtureUnionSource implements UnionSource
{
    /**
     * The two arms, pre-merged and stably ordered by id. Beta payloads carry a dummy
     * `@id` (exercises the non-null schemaRef branch); alpha payloads have none.
     *
     * @return Collection<int, ReviewItemFixture>
     */
    private function stream(): Collection
    {
        $alpha = collect(range(1, 4))->map(fn (int $n) => new ReviewItemFixture(
            source: 'alpha',
            id: "a{$n}",
            payload: ['label' => "Alpha {$n}"],
        ));

        $beta = collect(range(1, 4))->map(fn (int $n) => new ReviewItemFixture(
            source: 'beta',
            id: "b{$n}",
            payload: ['label' => "Beta {$n}", '@id' => "swc://fixture/beta/{$n}"],
        ));

        // Merge, then stable-sort by id so the cursor keys off a monotonic field.
        return $alpha->concat($beta)->sortBy->id->values();
    }

    public function index(UnionQuery $query): CursorPaginatorContract
    {
        $stream = $this->stream();

        // Optional `source` facet: an opaque filter the service (not frame) interprets.
        if (isset($query->filters['source'])) {
            $stream = $stream->where('source', $query->filters['source'])->values();
        }

        $cursor = $query->cursor !== null ? Cursor::fromEncoded($query->cursor) : null;

        // Offset the merged stream past the cursor's last-seen id.
        if ($cursor !== null) {
            $lastId = $cursor->parameter('id');
            $offset = $stream->search(fn (ReviewItemFixture $item) => $item->id === $lastId);
            $stream = $offset === false ? $stream : $stream->slice($offset + 1)->values();
        }

        // Take perPage + 1 so the paginator can detect (and cursor to) the next page.
        $slice = $stream->take($query->perPage + 1)->values();

        return new CursorPaginator(
            $slice,
            $query->perPage,
            $cursor,
            ['parameters' => ['id']],
        );
    }

    public function find(string $source, string $id): ?ResolvedUnionItem
    {
        $item = $this->stream()->first(
            fn (ReviewItemFixture $candidate) => $candidate->source === $source && $candidate->id === $id
        );

        if ($item === null) {
            return null;
        }

        return new ResolvedUnionItem(
            item: $item,
            schemaRef: $item->payload['@id'] ?? null,
        );
    }
}
