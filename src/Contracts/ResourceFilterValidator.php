<?php

namespace Schemastud\Frame\Contracts;

use Schemastud\Frame\Registry\ResourceDefinition;

/** Optional saved-query validation capability of a resource's declared filter provider. */
interface ResourceFilterValidator
{
    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed> Validated parameters, ready to persist and apply.
     */
    public function validate(ResourceDefinition $resource, array $parameters): array;
}
