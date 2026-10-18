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
    |                             filter capabilities under {prefix}/resources/*.
    |                             CRUD uses FrameResourceHandlerResolver. Each resource
    |                             declares its own ResourceFilterProvider; saved views
    |                             reference another registered resource for ordinary CRUD.
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
    | Per-realm resource presentation overrides — MOVED (A4-C5, 2026-10-18)
    |--------------------------------------------------------------------------
    | `realm_resource_overrides` now lives at `beam.core.realm_resource_overrides`
    | (laravel-beam config/beam/core.php), its reader's home. Frame shipped this
    | key for a map it never read — beam's RealmResourceRegistry was always the
    | only reader, and frame's own ADR-0001 says a field on frame's contract must
    | be read by frame or by frame's own plug seam. A host still setting the old
    | `frame.realm_resource_overrides` spelling keeps working through beam's
    | fallback read until its migration lands; new hosts set only the beam key.
    */
];
