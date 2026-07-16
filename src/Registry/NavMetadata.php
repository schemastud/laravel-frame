<?php

namespace Schemastud\Frame\Registry;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Per-entry nav metadata (data only — frame ships no router). A host renders its
 * nav by mapping over the manifest; frame does not own URL structure.
 *
 * `section`/`navOrder`/`routeName` are the host-nav join keys: a host sitemap
 * auto-attaches this resource into the `section` node (ordered by `navOrder`) and
 * binds the generated leaf under `routeName`. All nullable — a resource that
 * declares no `section` simply does not auto-attach.
 */
#[TypeScript]
class NavMetadata extends Data
{
    public function __construct(
        public string $label,
        public ?string $group = null,
        public ?string $icon = null,
        public ?string $section = null,
        public ?int $navOrder = null,
        public ?string $routeName = null,
    ) {}
}
