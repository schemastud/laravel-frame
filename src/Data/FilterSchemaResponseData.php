<?php

namespace Schemastud\Frame\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FilterSchemaResponseData extends Data
{
    /** @param array<string, mixed> $data The resource's JSON Schema document. */
    public function __construct(
        public array $data,
        public ?string $savedViewsResource = null,
    ) {}
}
