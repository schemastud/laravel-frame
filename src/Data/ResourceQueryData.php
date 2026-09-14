<?php

namespace Schemastud\Frame\Data;

use Schemastud\DataSchemas\Attributes\Description;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/** Shared query controls; the provider declares the resource's filter vocabulary. */
class ResourceQueryData extends Data
{
    public function __construct(
        #[Description('A declared filter variant of this resource, used for schema, execution and saved views.')]
        public string|Optional|null $filterVariant = new Optional,
    ) {}
}
