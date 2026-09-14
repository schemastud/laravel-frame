<?php

namespace Schemastud\Frame\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FilterOptionsResponseData extends Data
{
    /** @param list<FilterOptionData> $data */
    public function __construct(
        #[DataCollectionOf(FilterOptionData::class)]
        public array $data,
    ) {}
}
