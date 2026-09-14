<?php

namespace Schemastud\Frame\Data;

use Schemastud\DataSchemas\Attributes\Description;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class FilterOptionsQueryData extends Data
{
    public function __construct(
        #[Description('Search within the addressed resource option source.')]
        public string|Optional|null $search = new Optional,
    ) {}
}
