<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

/** A declared action input: the form a `reload`-style action renders, with a mapped wire key. */
class SampleActionInputData extends Data
{
    public function __construct(
        #[MapInputName('amount_usd')]
        public float $amountUsd,
    ) {}
}
