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
     * Return an immutable copy with the given fields overlaid — an agnostic copy-wither. Every param is
     * nullable and defaults to null meaning "keep the current value"; a non-null argument replaces that
     * field. The nested {@see NavMetadata} is likewise overlaid field-by-field and rebuilt fresh, so the
     * copy shares no mutable nav object with the original.
     *
     * This is deliberately realm-agnostic: frame knows nothing about realms. A producer that wants a
     * realm-varied projection composes this wither itself (e.g. `$def->withOverrides(policy: $realmPolicy)`)
     * — the realm concept never enters frame.
     *
     * Named `withOverrides` (not `with`) because the spatie `Data` base class already reserves `with()`
     * for its additional-data hook (no-arg, returns array); this is the copy-wither.
     *
     * @param  'model'|'service'|null  $sourceKind
     * @param  class-string|null  $model
     * @param  class-string|null  $source
     * @param  class-string|null  $data
     * @param  class-string|null  $query
     * @param  class-string|null  $editData
     * @param  'enriched'|'bare'|null  $form
     * @param  'single'|'subnav'|'master-detail'|null  $layout
     */
    public function withOverrides(
        ?string $key = null,
        ?string $sourceKind = null,
        ?string $model = null,
        ?string $source = null,
        ?string $data = null,
        ?bool $creatable = null,
        ?string $query = null,
        ?string $editData = null,
        ?string $policy = null,
        ?string $form = null,
        ?string $layout = null,
        ?bool $deletable = null,
        ?bool $editable = null,
        ?bool $showable = null,
        // NavMetadata overlay — each rebuilds the nav field-by-field:
        ?string $label = null,
        ?string $group = null,
        ?string $icon = null,
        ?string $section = null,
        ?int $navOrder = null,
        ?string $routeName = null,
    ): static {
        return new static(
            key: $key ?? $this->key,
            sourceKind: $sourceKind ?? $this->sourceKind,
            model: $model ?? $this->model,
            source: $source ?? $this->source,
            data: $data ?? $this->data,
            creatable: $creatable ?? $this->creatable,
            query: $query ?? $this->query,
            editData: $editData ?? $this->editData,
            policy: $policy ?? $this->policy,
            form: $form ?? $this->form,
            nav: new NavMetadata(
                label: $label ?? $this->nav->label,
                group: $group ?? $this->nav->group,
                icon: $icon ?? $this->nav->icon,
                section: $section ?? $this->nav->section,
                navOrder: $navOrder ?? $this->nav->navOrder,
                routeName: $routeName ?? $this->nav->routeName,
            ),
            layout: $layout ?? $this->layout,
            deletable: $deletable ?? $this->deletable,
            editable: $editable ?? $this->editable,
            showable: $showable ?? $this->showable,
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
