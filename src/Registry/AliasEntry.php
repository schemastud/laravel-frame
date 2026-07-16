<?php

namespace Schemastud\Frame\Registry;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One back-compat redirect, emitted in the manifest so the JS router is a pure
 * renderer — no separate client alias table. `from` → `to`; the JS host renders a
 * redirect route for each.
 *
 * Three shapes, all flat:
 *  - static path→path (`from:'/review-queue', to:'/review'`);
 *  - param-forwarding — a `:id` in both `from` and `to` interpolates client-side
 *    (`from:'/assistants/:id', to:'/threads/assistants/:id'`);
 *  - query-preserving (`preserveQuery:true`) — the client carries `?filter[...]`
 *    through the redirect.
 *
 * A by-name data lookup (e.g. `/proof/food-safety` → `/walkthrough/<circuitId>`)
 * is NOT a distinct shape: it is resolved SERVER-SIDE at emit — the host bakes the
 * looked-up `to` straight into a static entry, so the client never fetches.
 */
#[TypeScript]
class AliasEntry extends Data
{
    public function __construct(
        public string $from,
        public string $to,
        public bool $preserveQuery = false,
    ) {}
}
