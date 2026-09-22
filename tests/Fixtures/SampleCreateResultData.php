<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Spatie\LaravelData\Data;

class SampleCreateResultData extends Data
{
    public function __construct(
        public SampleResourceData $record,
        public string $receipt,
    ) {}
}
