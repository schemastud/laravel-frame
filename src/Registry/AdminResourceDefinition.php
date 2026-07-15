<?php

namespace Schemastud\Frame\Registry;

use Schemastud\Frame\Attributes\AdminResource;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The wiring behind one editable resource key — "registry C" (ticket 02). A
 * net-new #[TypeScript] Data DTO that references-and-widens a data-filters
 * ResourceDefinition ({key, model, data, query}) with the net-new editor fields
 * ({editData, policy, form, nav}), WITHOUT mutating that class in place.
 *
 * Served at GET /frame/manifest as AdminResourceDefinition[] and generated to TS so
 * the frontend manifest entry IS the backend projection (generate-once parity). The
 * one frontend overlay not carried here is `columns` (host-supplied FrameColumn[],
 * merged frontend-side — columns are not backend-derivable until x-column graduates).
 */
#[TypeScript]
class AdminResourceDefinition extends Data
{
    /**
     * @param  string  $key  resource slug
     * @param  class-string  $model  Eloquent model
     * @param  class-string  $data  read/index-projection Data class (list rows)
     * @param  class-string|null  $query  data-filters query class (optional filter schema)
     * @param  class-string|null  $editData  rare escape-hatch edit DTO
     * @param  string|null  $policy  ability/policy key the injected can() resolves against
     * @param  'splicewire'|'raw'  $form  per-resource default form mode
     */
    public function __construct(
        public string $key,
        public string $model,
        public string $data,
        public ?string $query,
        public ?string $editData,
        public ?string $policy,
        public string $form,
        public NavMetadata $nav,
    ) {}

    /**
     * Build a definition from an #[AdminResource]-annotated Data class. The
     * annotated class itself becomes `data` (the single-class read+edit default).
     *
     * @param  class-string  $dataClass
     */
    public static function fromAttribute(string $dataClass, AdminResource $attribute): self
    {
        return new self(
            key: $attribute->key,
            model: $attribute->model,
            data: $dataClass,
            query: $attribute->query,
            editData: $attribute->editData,
            policy: $attribute->policy,
            form: $attribute->form,
            nav: new NavMetadata(
                label: $attribute->label,
                group: $attribute->group,
                icon: $attribute->icon,
            ),
        );
    }
}
