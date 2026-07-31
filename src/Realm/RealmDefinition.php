<?php

namespace Schemastud\Frame\Realm;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A first-class realm — the single value the backend manifest builder and the
 * frontend route generator both read, replacing the bare `'admin'|'tenant'` string
 * that was threaded ad-hoc (realm-architecture ticket 01).
 *
 * A realm is "a manifest + a guarded route tree": this DTO carries the identity and
 * routing axes of that tree and NOTHING host-opinionated — no realm names, no RBAC,
 * no tenancy policy. The foundation owns the *shape*; a host (or, per ADR-0092, the
 * beam realm kit) supplies the concrete instances (operator · tenant · user · docs).
 *
 * @property string $key stable realm identity; the manifest route default + resource-realm map key
 * @property string $routeBase the SPA mount base for this realm's generated route tree (`/admin`, `/`, `/settings`)
 * @property string|null $guard the default host guard key wrapping this realm's leaves (`root` → RequireRoot; null = the shell's own auth)
 * @property bool $central whether this is THE central/operator realm (never tenant-scoped) — the
 *                         one bit the route builder reads; identity otherwise rides `$key`
 *                         (RealmScope retired, ADR-0156 amendment / realm-architecture ticket 08)
 * @property list<string> $stack the realm keys this realm COMPOSES THROUGH, ordered bottom→top — the
 *                               realms it layers over so its manifest/resources source from the
 *                               topmost resolvable one. Empty (the default) = a realm sources itself.
 *                               The `user` realm stacks on `['tenant']` when collapsed into the
 *                               workspace; `[]` once separated. Replaces the retired
 *                               `collapses()`/`effective()` special-case (realm-architecture ticket 08,
 *                               slice E) — a general composition axis, not a user↔tenant special case.
 *
 * Tenant resolvability is NOT carried here — it is a deployment-shape concern (is a tenant resolvable on
 * this install?), re-homed onto an injected `Splicewire\Beam\Realm\Contracts\TenantResolver` so the
 * agnostic shape stays purely structural (realm-architecture ticket 08, slice B).
 */
#[TypeScript]
class RealmDefinition extends Data
{
    /** @param list<string> $stack */
    public function __construct(
        public string $key,
        public string $routeBase,
        public ?string $guard,
        public bool $central,
        public array $stack = [],
    ) {}
}
