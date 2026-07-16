<?php

namespace Schemastud\Frame\Registry;

use InvalidArgumentException;
use Schemastud\Frame\Attributes\AdminResource;
use Schemastud\Frame\Contracts\UnionSource;
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
     * @param  'model'|'service'  $sourceKind  discriminator — an Eloquent model, or a custom UnionSource
     * @param  class-string|null  $model  Eloquent model (null for a service-backed union resource)
     * @param  class-string|null  $source  UnionSource class-string (null for a model-backed resource)
     * @param  class-string  $data  read/index-projection Data class (list rows)
     * @param  bool  $creatable  whether the host may emit a create affordance (false for a union)
     * @param  class-string|null  $query  data-filters query class (optional filter schema)
     * @param  class-string|null  $editData  rare escape-hatch edit DTO
     * @param  string|null  $policy  ability/policy key the injected can() resolves against
     * @param  'splicewire'|'raw'  $form  per-resource default form mode
     */
    public function __construct(
        public string $key,
        public string $sourceKind,
        public ?string $model,
        public ?string $source,
        public string $data,
        public bool $creatable,
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
     * Branches on backing: a `source` yields a `service` union resource (model
     * nulled, editData/query forced null, not creatable — a union is read + delegated
     * -act only); otherwise a `model`-backed resource behaves exactly as before.
     *
     * @param  class-string  $dataClass
     */
    public static function fromAttribute(string $dataClass, AdminResource $attribute): self
    {
        if ($attribute->source !== null) {
            return new self(
                key: $attribute->key,
                sourceKind: 'service',
                model: null,
                source: $attribute->source,
                data: $dataClass,
                creatable: false,
                query: null,
                editData: null,
                policy: $attribute->policy,
                form: $attribute->form,
                nav: new NavMetadata(
                    label: $attribute->label,
                    group: $attribute->group,
                    icon: $attribute->icon,
                    section: $attribute->section,
                    navOrder: $attribute->navOrder,
                    routeName: $attribute->routeName,
                ),
            );
        }

        return new self(
            key: $attribute->key,
            sourceKind: 'model',
            model: $attribute->model,
            source: null,
            data: $dataClass,
            creatable: true,
            query: $attribute->query,
            editData: $attribute->editData,
            policy: $attribute->policy,
            form: $attribute->form,
            nav: new NavMetadata(
                label: $attribute->label,
                group: $attribute->group,
                icon: $attribute->icon,
                section: $attribute->section,
                navOrder: $attribute->navOrder,
                routeName: $attribute->routeName,
            ),
        );
    }

    /**
     * Lazily resolve the backing UnionSource from the container at REQUEST time
     * (never eagerly at boot), so it can take constructor injection. Throws for a
     * model-backed resource, which has no source.
     */
    public function resolveSource(): UnionSource
    {
        if ($this->source === null) {
            throw new InvalidArgumentException(
                "Resource [{$this->key}] is model-backed (sourceKind: {$this->sourceKind}); it has no UnionSource."
            );
        }

        return app($this->source);
    }
}
