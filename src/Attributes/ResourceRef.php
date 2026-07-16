<?php

namespace Schemastud\Frame\Attributes;

use Attribute;
use Schemastud\Frame\Strategies\ResourceRefStrategy;

/**
 * Declares that an edit-DTO property references rows of another registered frame
 * resource — projected to the `x-stud-resource-ref` JSON-Schema vendor keyword by
 * {@see ResourceRefStrategy}, embedded in the one `forRequest()` schema (the
 * #[Widget]->x-stud-widget way), NOT a sidecar uiSchema. The frontend
 * ResourceRefWidget fetches the referenced resource's index to build the picker
 * options; `scope` rides that resource's existing data-filters `query` as pre-applied
 * filter params (NOT new filtering).
 *
 *   #[ResourceRef('plans', value: 'slug', label: 'name')]
 *   #[ResourceRef('starter-packs', value: 'slug', label: 'name', multiple: true, scope: ['status' => 'active'])]
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ResourceRef
{
    /**
     * @param  string  $resource  a registered frame resource key (its index is fetched for options)
     * @param  string  $value  the row field submitted as the option value
     * @param  string  $label  the row field displayed to the user
     * @param  bool  $multiple  single vs multi-select (default single)
     * @param  array<string, string|int|bool>|null  $scope  pre-applied filter values passed as query params to the referenced index
     */
    public function __construct(
        public string $resource,
        public string $value = 'id',
        public string $label = 'name',
        public bool $multiple = false,
        public ?array $scope = null,
    ) {}
}
