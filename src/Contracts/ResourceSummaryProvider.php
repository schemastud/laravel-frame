<?php

namespace Schemastud\Frame\Contracts;

use Schemastud\Frame\Data\SummaryResponseData;
use Schemastud\Frame\Registry\ResourceDefinition;

/**
 * A resource's declared summary capability — its compressed rendering as figures — resolved after its
 * access gate, exactly as {@see ResourceFilterProvider} is.
 *
 * Returning `null` DECLINES: the resource names a provider but this backing cannot summarize (a source
 * that only streams and cannot count, for instance), and the route answers 404 the way `filters/options`
 * does for a resource with no provider. Frame never invents a figure for a provider that declined.
 */
interface ResourceSummaryProvider
{
    public function summary(ResourceDefinition $resource): ?SummaryResponseData;
}
