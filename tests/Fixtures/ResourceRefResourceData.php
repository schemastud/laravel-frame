<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Schemastud\Frame\Attributes\ResourceRef;
use Spatie\LaravelData\Data;

/**
 * A fixture exercising #[ResourceRef] -> x-stud-resource-ref: a single-select
 * plan reference (default multiple:false, no scope) and a multi-select scoped
 * starter-pack reference.
 */
class ResourceRefResourceData extends Data
{
    public function __construct(
        public string $title,
        #[ResourceRef('plans', value: 'slug', label: 'name')]
        public string $plan = '',
        #[ResourceRef('starter-packs', value: 'slug', label: 'name', multiple: true, scope: ['status' => 'active'])]
        public array $starterPacks = [],
    ) {}
}
