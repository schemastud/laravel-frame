<?php

namespace Schemastud\Frame\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FilterVariantsData extends Data
{
    /** @param list<FilterVariantData> $variants */
    public function __construct(public string $resource, public array $variants) {}
}
