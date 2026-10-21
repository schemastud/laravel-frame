<?php

namespace Schemastud\Frame\Contracts;

use Illuminate\Http\Request;
use Schemastud\Frame\Registry\ResourceDefinition;

/**
 * Optional address-dependent schema; null retains the configured Data generator chain.
 * The Request's validated purpose is write (also when omitted) or read. A producer
 * with public/custom/narrowed record shapes must return their matching authorized
 * schema here; the default read fallback describes ResourceDefinition::data.
 */
interface ResourceSchemaResolver
{
    public function schema(ResourceDefinition $resource, Request $request): ?array;
}
