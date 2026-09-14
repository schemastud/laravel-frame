<?php

namespace Schemastud\Frame\Contracts;

use Schemastud\Frame\Data\FilterOptionsResponseData;
use Schemastud\Frame\Data\FilterSchemaResponseData;
use Schemastud\Frame\Data\FilterVariantsResponseData;
use Schemastud\Frame\Registry\ResourceDefinition;

/** A resource's declared filter capability, resolved after its access gate. */
interface ResourceFilterProvider
{
    public function schema(ResourceDefinition $resource, ?string $variant = null): FilterSchemaResponseData;

    public function options(ResourceDefinition $resource, string $ref, ?string $search = null): FilterOptionsResponseData;

    public function variants(ResourceDefinition $resource): FilterVariantsResponseData;
}
