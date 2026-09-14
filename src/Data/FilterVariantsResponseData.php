<?php

namespace Schemastud\Frame\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FilterVariantsResponseData extends Data
{
    public function __construct(public FilterVariantsData $data) {}
}
