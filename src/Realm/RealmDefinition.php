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
 * @property string      $key       stable realm identity; the manifest route default + resource-realm map key
 * @property string      $routeBase the SPA mount base for this realm's generated route tree (`/admin`, `/`, `/settings`)
 * @property string|null $guard     the default host guard key wrapping this realm's leaves (`root` → RequireRoot; null = the shell's own auth)
 * @property RealmScope  $scope     the identity axis — `central` · `tenant` · `user`
 * @property bool        $tenancy   whether this realm resolves a tenant (per-realm optional; not a global switch)
 */
#[TypeScript]
class RealmDefinition extends Data
{
    public function __construct(
        public string $key,
        public string $routeBase,
        public ?string $guard,
        public RealmScope $scope,
        public bool $tenancy,
    ) {}
}
