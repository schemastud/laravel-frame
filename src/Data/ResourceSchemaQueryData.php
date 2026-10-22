<?php

namespace Schemastud\Frame\Data;

use Schemastud\DataSchemas\Attributes\Description;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Data;

class ResourceSchemaQueryData extends Data
{
    public function __construct(
        #[Description('write projects the edit DTO for input; read projects the read DTO for output. Omitted means write.')]
        #[In('write', 'read')]
        public string $purpose = 'write',
    ) {}
}
