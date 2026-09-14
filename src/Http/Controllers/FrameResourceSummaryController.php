<?php

namespace Schemastud\Frame\Http\Controllers;

use Illuminate\Http\Request;
use Rushing\LaravelDataSchemasScribe\Attributes\ResponseFromData;
use Schemastud\Frame\Data\SummaryResponseData;
use Schemastud\Frame\Summary\ResourceSummaries;

/** The summary capability of an addressed Frame resource. */
class FrameResourceSummaryController
{
    public function __construct(private ResourceSummaries $summaries) {}

    /** Get resource summary */
    #[ResponseFromData(SummaryResponseData::class)]
    public function show(Request $request): array
    {
        return $this->summaries->summary((string) $request->route('resource'))->toArray();
    }
}
