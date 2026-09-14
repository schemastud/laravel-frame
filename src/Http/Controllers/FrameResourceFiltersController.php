<?php

namespace Schemastud\Frame\Http\Controllers;

use Illuminate\Http\Request;
use Rushing\LaravelDataSchemasScribe\Attributes\QueryFromData;
use Rushing\LaravelDataSchemasScribe\Attributes\ResponseFromData;
use Schemastud\Frame\Data\FilterOptionsQueryData;
use Schemastud\Frame\Data\FilterOptionsResponseData;
use Schemastud\Frame\Data\FilterSchemaResponseData;
use Schemastud\Frame\Data\FilterVariantsResponseData;
use Schemastud\Frame\Filters\ResourceFilters;

/** Filter capabilities of an addressed Frame resource. */
class FrameResourceFiltersController
{
    public function __construct(private ResourceFilters $filters) {}

    /** Get resource filter schema */
    #[ResponseFromData(FilterSchemaResponseData::class)]
    public function schema(Request $request): array
    {
        return $this->filters->schema((string) $request->route('resource'), $request->route('variant'))->toArray();
    }

    /** Get resource filter options */
    #[QueryFromData(FilterOptionsQueryData::class)]
    #[ResponseFromData(FilterOptionsResponseData::class)]
    public function options(Request $request): array
    {
        $key = (string) $request->route('resource');
        $this->filters->definition($key);
        $query = FilterOptionsQueryData::validateAndCreate($request->query());

        return $this->filters->options(
            $key,
            (string) $request->route('ref'),
            is_string($query->search) ? $query->search : null,
        )->toArray();
    }

    /** List resource filter variants */
    #[ResponseFromData(FilterVariantsResponseData::class)]
    public function variants(Request $request): array
    {
        return $this->filters->variants((string) $request->route('resource'))->toArray();
    }
}
