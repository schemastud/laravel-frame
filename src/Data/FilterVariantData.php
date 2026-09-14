<?php

namespace Schemastud\Frame\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FilterVariantData extends Data
{
    public function __construct(
        public string $key,
        public string $resource,
        public bool $canonical,
        public bool $sameAsCanonical,
    ) {}
}
