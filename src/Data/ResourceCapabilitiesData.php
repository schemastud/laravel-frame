<?php

namespace Schemastud\Frame\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Advisory policy answers; endpoints still authorize every operation. */
#[TypeScript]
class ResourceCapabilitiesData extends Data
{
    public function __construct(
        public bool $create,
        public bool $update,
        public bool $delete,
    ) {}
}
