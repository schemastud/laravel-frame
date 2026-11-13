<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server surface (routes)
    |--------------------------------------------------------------------------
    | Until owner-review ticket 16, the provider serves both api_root (stud/frame)
    | and the retained route_prefix (frame), behind the same middleware. Changing
    | api_root moves the new exposure only. A host fencing by URL must cover both
    | roots or disable automatic registration and compose its boundary explicitly.
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
    'api_root' => 'stud/frame',
    // Legacy exposure only, retained until owner-review ticket 16.
    'route_prefix' => 'frame',
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Widget vocabulary file
    |--------------------------------------------------------------------------
    | Where the host's widget vocabulary lives: the names its frame kit binds, per
    | context, with their RJSF stock node types (frame-foundation ticket 05, ruling 2).
    | The file is GENERATED and committed: `pnpm frame:vocabulary --write` runs
    | @schemastud/frame's `frame-vocabulary` bin, and `--out` moves it. PHP reads it
    | through Schemastud\Frame\Vocabulary\WidgetVocabulary; a missing file is a state
    | the reader reports, not an error. A relative path resolves against base_path().
    */
    'vocabulary' => resource_path('frame/vocabulary.json'),

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
