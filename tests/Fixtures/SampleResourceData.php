<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Schemastud\Frame\Attributes\ReadOnlyField;
use Schemastud\Frame\Attributes\Widget;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;

/**
 * A representative single (read+edit) resource Data class exercising frame's generic
 * property attributes: #[Widget] -> x-stud-widget, #[ReadOnlyField] -> readOnly, and
 * spatie #[Computed] dropped from forRequest. The resource DECLARATION (which model,
 * nav, layout) is the producer's concern (beam) — frame only projects the shape.
 */
class SampleResourceData extends Data
{
    public function __construct(
        public string $title,
        #[Widget('textarea')]
        public string $body = '',
        #[Widget('splicewire-enrich', options: ['tone' => 'warm'])]
        public string $interpretation = '',
        #[ReadOnlyField]
        public string $reference = '',
        #[Computed]
        public string $slug = '',
    ) {}
}
