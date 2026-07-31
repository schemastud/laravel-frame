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
];
