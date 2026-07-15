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
    | Manifest route
    |--------------------------------------------------------------------------
    | Whether the package registers GET {prefix}/manifest, and the middleware +
    | prefix it applies. A host that mounts the manifest under its own gated group
    | should set 'register_route' => false and wire FrameManifestController itself
    | (numero mounts it under the operator group behind can:bypass-marquee).
    */
    'register_route' => true,
    'route_prefix' => 'frame',
    'middleware' => ['web'],
];
