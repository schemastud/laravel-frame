<?php

namespace Schemastud\Frame\Attributes;

use Attribute;

/**
 * Marks a Data class as an editable admin resource — the single source of truth
 * for frame's manifest (derive, don't hand-author twice). Placed on the ONE
 * read+edit Data class per resource (the single-class zero-drift default); the
 * annotated class itself becomes the resource's `data` (read/index projection)
 * and, absent an `editData` escape hatch, its edit shape too.
 *
 * The registry reflects this attribute at boot to build an AdminResourceDefinition.
 * `key`/`model` are required; the rest are the net-new editor fields that widen a
 * data-filters ResourceDefinition without mutating it. `query` optionally names a
 * data-filters query class so the ListShell facets bar can ride an existing filter
 * schema.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AdminResource
{
    /**
     * @param  string  $key  resource slug ('axis-leaf', 'layers', …)
     * @param  class-string  $model  the Eloquent model
     * @param  string  $label  nav label
     * @param  string|null  $group  nav group heading
     * @param  string|null  $icon  nav icon key
     * @param  'splicewire'|'raw'  $form  per-resource default form mode
     * @param  class-string|null  $editData  rare escape-hatch edit DTO (input-shape divergence)
     * @param  string|null  $policy  ability/policy key the injected can() resolves against
     * @param  class-string|null  $query  data-filters query class (optional filter schema)
     */
    public function __construct(
        public string $key,
        public string $model,
        public string $label,
        public ?string $group = null,
        public ?string $icon = null,
        public string $form = 'raw',
        public ?string $editData = null,
        public ?string $policy = null,
        public ?string $query = null,
    ) {}
}
