<?php

namespace Schemastud\Frame\Registry;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A readable Collection choice for a resource; its address is an unversioned schema stem. */
#[TypeScript]
class ResourceCollectionAddress extends Data
{
    public function __construct(
        public string $stem,
        public string $label,
    ) {}
}
