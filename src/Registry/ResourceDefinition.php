<?php

namespace Schemastud\Frame\Registry;

use InvalidArgumentException;
use Schemastud\Frame\Contracts\UnionSource;
use Schemastud\Frame\Http\Controllers\FrameManifestController;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The agnostic projection contract for one editable resource key — "registry C"
 * (ticket 02). Frame's manifest machinery ({@see FrameManifestController})
 * serves an array of these at GET /frame/manifest, generated to TS so the frontend
 * manifest entry IS the backend projection (generate-once parity).
 *
 * This is a GENERIC manifest contract — it names {key, model|source, data, nav, …}
 * but knows nothing about the opinion that produced it. A producer (a CMS engine's
 * resource declaration + registry, or any other) reflects its own declaration and
 * HANDS one of these to frame; frame renders what it is handed and never names a
 * model, a policy, or an opinion about editing. Frame owns the contract because
 * frame's own machinery consumes it (it cannot import a producer type that sits above
 * it in the dependency graph).
 *
 * The one frontend overlay not carried here is `columns` (host-supplied FrameColumn[],
 * merged frontend-side — columns are not backend-derivable until x-column graduates).
 */
#[TypeScript]
class ResourceDefinition extends Data
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
     * @param  'enriched'|'bare'  $form  per-resource default form mode
     * @param  'single'|'subnav'|'master-detail'|null  $layout  inner-layout grammar emitted on the ContextManifest (null = the socket's SingleColumn fallback)
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
        public ?string $layout = null,
    ) {}

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
