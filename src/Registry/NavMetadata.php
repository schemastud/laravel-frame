<?php

namespace Schemastud\Frame\Registry;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Per-entry nav metadata (data only — frame ships no router). A host renders its
 * nav by mapping over the manifest; frame does not own URL structure.
 */
#[TypeScript]
class NavMetadata extends Data
{
    public function __construct(
        public string $label,
        public ?string $group = null,
        public ?string $icon = null,
    ) {}
}
