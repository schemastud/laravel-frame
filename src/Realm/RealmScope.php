<?php

namespace Schemastud\Frame\Realm;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The identity axis of a {@see RealmDefinition} — the load-bearing distinction the
 * realm model turns on (realm-architecture PRD):
 *
 *  - `central` — a single, non-tenant-scoped context (the operator console).
 *  - `tenant`  — the current workspace; one of potentially many.
 *  - `user`    — the account/identity *across* workspaces (per-user, not per-workspace).
 *
 * On a single-tenant instance the `tenant` and `user` scopes collapse to one identity;
 * on a multi-tenant SaaS they separate. Scope is realm-agnostic machinery — the
 * foundation names the axis; a host's realm kit names the concrete realms.
 */
#[TypeScript]
enum RealmScope: string
{
    case Central = 'central';
    case Tenant = 'tenant';
    case User = 'user';
}
