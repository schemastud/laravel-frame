<?php

namespace Schemastud\Frame\Contracts;

use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * A custom source backing a frame resource — fuses N underlying models/services into
 * one list+detail resource. Named by an #[AdminResource(source: …)] class-string and
 * container-resolved at REQUEST time (app($def->source)), never eagerly at boot, so
 * it can take constructor injection (tenant connection, the sub-source repos). Frame
 * owns no list/record runtime; a host controller calls this, frame only wires it.
 */
interface UnionSource
{
    /**
     * Merge N sub-sources → one paginated page of the resource's envelope. The
     * service owns the merge, sort, filter, and pagination — frame does not.
     */
    public function index(UnionQuery $query): CursorPaginator;

    /**
     * Load ONE item by discriminator + id, WITH the (nullable) $ref its payload
     * resolves through. Returns null when (source, id) does not exist / is not
     * pending.
     */
    public function find(string $source, string $id): ?ResolvedUnionItem;
}
