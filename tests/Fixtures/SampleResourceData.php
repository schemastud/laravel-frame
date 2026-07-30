<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Schemastud\Frame\Attributes\AdminResource;
use Schemastud\Frame\Attributes\ReadOnlyField;
use Schemastud\Frame\Attributes\Widget;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;

/**
 * A representative single (read+edit) resource class exercising every frame
 * attribute: #[AdminResource] discovery, #[Widget] -> x-stud-widget,
 * #[ReadOnlyField] -> readOnly, and spatie #[Computed] dropped from forRequest.
 */
#[AdminResource(
    key: 'sample',
    model: SampleModel::class,
    label: 'Samples',
    group: 'Testing',
    icon: 'flask',
    form: 'raw',
    section: 'lab',
    navOrder: 3,
    routeName: 'samples.index',
    layout: 'subnav',
)]
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
