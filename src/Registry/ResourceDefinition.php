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
     * @param  bool  $deletable  whether the host may emit a delete affordance and the generic handler honours a Frame destroy (independent of $creatable — a resource may be delete-only, e.g. a list you may prune but not create/edit). Defaults true so every existing resource's delete follows its create gate; a producer projects it explicitly to open destroy on an otherwise not-creatable resource.
     * @param  bool  $editable  whether the host may emit an edit affordance and the generic handler honours a Frame update (independent of $creatable — a resource may be create-and-delete-only, never edited in place, e.g. an invitation: sent + revoked but not edited). Defaults true so every existing resource's edit follows its create gate; a producer projects it explicitly to CLOSE in-place edit on an otherwise creatable resource.
     * @param  bool  $showable  whether the generic handler serves a per-record detail (`records/{id}`, show), independent of $editable — so a READ-ONLY resource (no create/edit/delete) can still expose a detail view under a read gate, and an editable resource always shows. Defaults true (readable ⇒ showable): every existing resource already served show under its edit gate and keeps doing so, while a read-only resource — previously list-only because show shared the edit gate — now serves detail. A producer projects it explicitly to false to CLOSE the detail view on an otherwise readable resource.
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
        public bool $deletable = true,
        public bool $editable = true,
        public bool $showable = true,
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
