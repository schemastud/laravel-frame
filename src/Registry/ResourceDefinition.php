<?php

namespace Schemastud\Frame\Registry;

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
     * @param  class-string|null  $model  Eloquent model (null for a service-backed union resource)
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
     * @param  'frame'|'host'  $createAffordance  WHERE this resource's create affordance lives, and the only new slot here: `'frame'` (the default, and today's behaviour) means frame's own list Toolbar emits the "New …" button; `'host'` means the host's page chrome owns it — a title-row button, a reveal-once dialog — so frame emits none. It is a PRESENTATION slot, deliberately not a capability one: $creatable already answers "may this be created at all", and a resource can be perfectly creatable while its affordance lives somewhere frame cannot see. The two are combined into one resolved value on the {@see ContextManifest}, never on the client, so `creatable` keeps exactly one spelling.
     */
    public function __construct(
        public string $key,
        public ?string $model,
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
        public string $createAffordance = 'frame',
    ) {}

    /**
     * The RESOLVED create affordance emitted onto this resource's {@see ContextManifest} — the one
     * value a frame shell reads.
     *
     * Two declared facts collapse into it: a resource that is not `$creatable` at all cannot have a
     * frame-emitted create button, and a `$creatable` resource may still declare that the affordance
     * is the HOST's. Resolving them HERE rather than on the client is what keeps `creatable` with a
     * single spelling — a second client-side flag meaning "sort of creatable" is precisely how a gate
     * stops meaning what its name says.
     *
     * @return 'frame'|'host'
     */
    public function resolvedCreateAffordance(): string
    {
        return $this->creatable && $this->createAffordance === 'frame' ? 'frame' : 'host';
    }

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
     * @param  class-string|null  $model
     * @param  class-string|null  $data
     * @param  class-string|null  $query
     * @param  class-string|null  $editData
     * @param  'enriched'|'bare'|null  $form
     * @param  'single'|'subnav'|'master-detail'|null  $layout
     * @param  'frame'|'host'|null  $createAffordance
     */
    public function withOverrides(
        ?string $key = null,
        ?string $model = null,
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
        ?string $createAffordance = null,
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
            model: $model ?? $this->model,
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
            createAffordance: $createAffordance ?? $this->createAffordance,
        );
    }
}
