<?php

namespace Schemastud\Frame\Data;

use Schemastud\DataSchemas\Attributes\Description;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/** Shared query controls; the provider declares the resource's filter vocabulary. */
class ResourceQueryData extends Data
{
    public function __construct(
        #[Description('A declared filter variant of this resource, used for schema, execution and saved views.')]
        public string|Optional|null $filterVariant = new Optional,
        #[Description('Page number for resources that use offset pagination.'), Min(1)]
        public int|Optional $page = new Optional,
        #[Description('Maximum number of rows to return per page.'), MapInputName('per_page'), Min(1), Max(100)]
        public int|Optional $per_page = new Optional,
        #[Description('Opaque continuation cursor returned by the previous page.')]
        public string|Optional|null $cursor = new Optional,
    ) {}
}
