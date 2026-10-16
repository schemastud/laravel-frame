<?php

namespace Schemastud\Frame\Contracts;

use Illuminate\Http\Request;
use Schemastud\Frame\Registry\ResourceDefinition;

/** Optional address-dependent form schema; null retains the configured Data generator chain. */
interface ResourceSchemaResolver
{
    public function schema(ResourceDefinition $resource, Request $request): ?array;
}
