<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server surface (routes)
    |--------------------------------------------------------------------------
    | Frame's whole server surface mounts under one prefix + middleware, so a host
    | moves the CMS by flipping 'route_prefix' (e.g. 'frame' → '~/beam' — a '~/'
    | sentinel fences the gated admin off from public content routes) and gates it
    | via 'middleware'.
    |
    | - register_route:           master switch for the package route group
    |                             (manifest + resource socket). A host that mounts
    |                             everything under its own group sets this false.
    | - register_resource_routes: whether the group also ships the resource CRUD +
    |                             facets socket ({prefix}/resources/*,
    |                             {prefix}/filter-schema|filter-options|saved-filters).
    |                             The socket resolves its per-resource plug through the
    |                             host-bound FrameResourceHandlerResolver /
    |                             FrameFilterProvider contracts; a host still hand-rolling
    |                             its own resource endpoints sets this false (manifest
    |                             only). SavedFilterStore is optional — unbound falls back
    |                             to a transient saved-views stub.
    |
    | Resource DECLARATION + discovery is NOT frame's concern: a producer above frame
    | (the consuming CMS engine's resource registry) owns which resources exist and
    | binds frame's ResourceRegistry port. Frame only serves what it is handed.
    */
    'register_route' => true,
    'register_resource_routes' => true,
    'route_prefix' => 'frame',
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Per-realm resource presentation overrides (RDU-03)
    |--------------------------------------------------------------------------
    | A realm may PRESENT the same resource differently — a different label,
    | group, form, layout, or a read-only gate — WITHOUT the resource declaration
    | ever naming a realm (declarations stay realm-agnostic; frame stays agnostic).
    | The producer above frame (beam's RealmResourceRegistry) reads this map and
    | overlays a resource's projection for the target realm; frame is handed the
    | finished ResourceDefinition and never sees a realm.
    |
    | Shape: ['<realm>' => ['<resource-key>' => ['<presentation-field>' => <value>]]].
    | Only PRESENTATION fields overlay (label, group, icon, section, navOrder,
    | routeName, form, layout, editData, policy, query, readOnly, deletable,
    | editable, showable) — runtime fields (model/data/hooks) never vary by realm.
    | A non-null field overlays the base; an absent field inherits.
    |
    | Ships EMPTY — INERT by default (identity projection in every realm), no
    | behavior change until a host seeds real overrides.
    */
    'realm_resource_overrides' => [],
];
