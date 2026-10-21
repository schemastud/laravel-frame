<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Schemastud\DataSchemas\Attributes\Keyword;
use Schemastud\DataSchemas\Keywords;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;

class SchemaPurposeData extends Data
{
    #[Computed]
    public string $receipt;

    #[Keyword(Keywords::Hidden)]
    public string $privateValue = 'private';

    public function __construct(
        #[MapInputName('input_title'), MapOutputName('output_title')]
        public string $title = '',
        public string $approvalState = 'pending',
    ) {
        $this->receipt = 'derived';
    }
}
