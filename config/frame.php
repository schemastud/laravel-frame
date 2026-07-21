<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Resource classes
    |--------------------------------------------------------------------------
    | Explicit #[AdminResource]-annotated Data class-strings to register at boot.
    | Each is reflected for its #[AdminResource] attribute and becomes a manifest
    | entry. This is the deterministic discovery path (data-filters' ResourceRegistry
    | shape); attribute-less resources register imperatively via ->register().
    */
    'resources' => [
        // App\Data\LayerData::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Discover paths
    |--------------------------------------------------------------------------
    | Optional filesystem paths scanned at boot for #[AdminResource] classes (the
    | CompositionIntakeRegistry scan pattern). A path may point at a whole Data
    | directory; only annotated classes are registered.
    */
    'discover_paths' => [
        // app_path('Data'),
    ],

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
    */
    'register_route' => true,
    'register_resource_routes' => true,
    'route_prefix' => 'frame',
    'middleware' => ['web'],
];
