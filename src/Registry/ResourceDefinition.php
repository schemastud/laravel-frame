<?php

namespace Schemastud\Frame\Registry;

use Illuminate\Support\Str;
use Schemastud\DataSchemas\Attributes\Keyword;
use Schemastud\DataSchemas\Keywords;
use Schemastud\Frame\Http\Controllers\FrameManifestController;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\Hidden as HiddenFromTypeScript;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The agnostic projection contract for one editable resource key — "registry C"
 * (ticket 02). Frame's manifest machinery ({@see FrameManifestController})
 * serves an array of these at GET /frame/manifest, generated to TS so the frontend
 * manifest entry IS the backend projection (generate-once parity).
 *
 * This is a GENERIC manifest contract — it names {key, data, nav, affordances, …}
 * but knows nothing about the opinion that produced it. A producer (a CMS engine's
 * resource declaration + registry, or any other) reflects its own declaration and
 * HANDS one of these to frame; frame renders what it is handed and never names a
 * model, a policy, or an opinion about editing. Frame owns the contract because
 * frame's own machinery consumes it (it cannot import a producer type that sits above
 * it in the dependency graph).
 *
 * The one frontend overlay not carried here is `columns` (host-supplied FrameColumn[],
 * merged frontend-side — columns are not backend-derivable until x-column graduates).
 *
 * **The wire carries no PHP class-string** (ADR-0002, ADR-0004). Every constructor field that holds one is
 * either a SERVER-SIDE INPUT or a Data class projected to its generated type name:
 *
 *  - Server-side inputs — `$model`, `$query`, `$policy`, `$filterProvider`, `$summaryProvider` — carry
 *    `#[Hidden]` (laravel-data: dropped from `toArray()`/JSON), typescript-transformer's `#[Hidden]` (dropped
 *    from the emitted type) and `Keyword(Keywords::Hidden)` (dropped from the generated response schema).
 *    They stay public properties because the server reads them: {@see \Schemastud\Frame\Authorization\ResourceAuthorizer}
 *    resolves the write-gate subject from `$model`, the write and read gates resolve `$policy`, and the
 *    declared filter capability resolves `$query`. A browser has no use for an Eloquent class, a query
 *    builder class or a gate name, and the client's own authority is the injected `can()` plus the
 *    per-actor `can` on the {@see ContextManifest}.
 *  - Data classes — `$data`, `$editData`, `$createResultData` — stay class-strings in PHP and are projected
 *    through {@see GeneratedTypeName} on output, so the JSON says `Vendor.Package.Data.RowData` — the name
 *    `typescript:transform` emits — never `Vendor\Package\Data\RowData`.
 */
#[TypeScript]
class ResourceDefinition extends Data
{
    /**
     * @param  string  $key  resource slug
     * @param  class-string|null  $model  Eloquent model (null for a service-backed union resource). Server-side only: never on the wire, never in the TS type.
     * @param  class-string  $data  read/index-projection Data class (list rows). Class-string in PHP; on the wire, the generated type's dot-form name.
     * @param  bool  $creatable  whether the host may emit a create affordance (false for a union)
     * @param  bool  $deletable  whether the host may emit a delete affordance and the generic handler honours a Frame destroy (independent of $creatable — a resource may be delete-only, e.g. a list you may prune but not create/edit). Defaults true so every existing resource's delete follows its create gate; a producer projects it explicitly to open destroy on an otherwise not-creatable resource.
     * @param  bool  $editable  whether the host may emit an edit affordance and the generic handler honours a Frame update (independent of $creatable — a resource may be create-and-delete-only, never edited in place, e.g. an invitation: sent + revoked but not edited). Defaults true so every existing resource's edit follows its create gate; a producer projects it explicitly to CLOSE in-place edit on an otherwise creatable resource.
     * @param  bool  $showable  whether the generic handler serves a per-record detail (`records/{id}`, show), independent of $editable — so a READ-ONLY resource (no create/edit/delete) can still expose a detail view under a read gate, and an editable resource always shows. Defaults true (readable ⇒ showable): every existing resource already served show under its edit gate and keeps doing so, while a read-only resource — previously list-only because show shared the edit gate — now serves detail. A producer projects it explicitly to false to CLOSE the detail view on an otherwise readable resource.
     * @param  class-string|null  $query  data-filters query class (optional filter schema). Server-side only: never on the wire, never in the TS type (ADR-0004).
     * @param  class-string|null  $editData  rare escape-hatch edit DTO. Class-string in PHP (the schema endpoint reflects `editData ?? data`); on the wire, the generated type's dot-form name (ADR-0004).
     * @param  class-string<Data>|null  $createResultData  the Frame creation result; null uses the read projection. A custom handler may return a declared result such as a record plus a reveal-once receipt.
     * @param  string|null  $policy  ability/policy key the server-side gates resolve against — a Gate ability or a policy class-string. Server-side only: never on the wire, never in the TS type (ADR-0004).
     * @param  'enriched'|'bare'  $form  per-resource default form mode
     * @param  'single'|'subnav'|'master-detail'|null  $layout  inner-layout grammar emitted on the ContextManifest (null = the socket's SingleColumn fallback)
     * @param  string  $singularLabel  the resource's display SINGULAR — the noun a create affordance says ("New scaffold pack"). Empty (the default) ⇒ inflected from `$nav->label` (or the key). It exists because the inflector MANGLES mass/irregular nouns (`media` → "Medium"), which is the same reason the producer's own declaration carries the slot; declaring it here is how that declared word reaches a shell instead of dying in the docs generator. Display-only: it carries no capability and gates nothing.
     * @param  'frame'|'host'  $createAffordance  WHERE this resource's create affordance lives, and the only new slot here: `'frame'` (the default, and today's behaviour) means frame's own list Toolbar emits the "New …" button; `'host'` means the host's page chrome owns it — a title-row button, a reveal-once dialog — so frame emits none. It is a PRESENTATION slot, deliberately not a capability one: $creatable already answers "may this be created at all", and a resource can be perfectly creatable while its affordance lives somewhere frame cannot see. The two are combined into one resolved value on the {@see ContextManifest}, never on the client, so `creatable` keeps exactly one spelling.
     * @param  class-string<\Schemastud\Frame\Contracts\ResourceFilterProvider>|null  $filterProvider  the resource's declared filter capability. Server-side only: never on the wire, never in the TS type; a producer names it and frame container-makes it after the access gate.
     * @param  class-string<\Schemastud\Frame\Contracts\ResourceSummaryProvider>|null  $summaryProvider  the resource's declared summary capability (`resources/{resource}/summary`), mirroring `$filterProvider` slot for slot: hidden from the wire and the TS type, preserved by {@see withOverrides()}, resolved only after the access gate. Null ⇒ the route answers 404.
     */
    public function __construct(
        public string $key,
        #[Hidden, HiddenFromTypeScript, Keyword(Keywords::Hidden)]
        public ?string $model,
        #[WithTransformer(GeneratedTypeName::class)]
        public string $data,
        public bool $creatable,
        #[Hidden, HiddenFromTypeScript, Keyword(Keywords::Hidden)]
        public ?string $query,
        #[WithTransformer(GeneratedTypeName::class)]
        public ?string $editData,
        #[Hidden, HiddenFromTypeScript, Keyword(Keywords::Hidden)]
        public ?string $policy,
        public string $form,
        public NavMetadata $nav,
        public ?string $layout = null,
        public bool $deletable = true,
        public bool $editable = true,
        public bool $showable = true,
        public string $createAffordance = 'frame',
        public string $singularLabel = '',
        /** @var class-string<\Schemastud\Frame\Contracts\ResourceFilterProvider>|null */
        #[Hidden, HiddenFromTypeScript, Keyword(Keywords::Hidden)]
        public ?string $filterProvider = null,
        /** @var class-string<\Schemastud\Frame\Contracts\ResourceSummaryProvider>|null */
        #[Hidden, HiddenFromTypeScript, Keyword(Keywords::Hidden)]
        public ?string $summaryProvider = null,
        #[WithTransformer(GeneratedTypeName::class)]
        public ?string $createResultData = null,
    ) {}

    /** @return class-string<Data> */
    public function resolvedCreateResultData(): string
    {
        return $this->createResultData ?? $this->data;
    }

    /**
     * The RESOLVED singular noun emitted onto this resource's {@see ContextManifest} — what a
     * frame-emitted create affordance calls one record ("New scaffold pack").
     *
     * The declared word wins; absent one, the plural display label is inflected. Resolving it
     * SERVER-side is the point: the client has neither the label (a shell is handed its
     * ContextManifest, never the definition) nor an inflector, which is why frame's own toolbar
     * has been rendering the raw resource KEY — "New scaffold-packs".
     *
     * ⚠️ This never touches `$nav->label`. The plural label is what nav renders and it keeps one
     * spelling; this is a second, derived word for a different sentence.
     */
    public function resolvedSingularLabel(): string
    {
        if ($this->singularLabel !== '') {
            return $this->singularLabel;
        }

        return Str::singular($this->resolvedLabel());
    }

    /**
     * The RESOLVED plural display label — the declared nav label, or the key headlined when nav declares
     * none ("scaffold-packs" ⇒ "Scaffold Packs").
     *
     * It exists because the fallback was being spelled twice and drifting: {@see resolvedSingularLabel()}
     * inflected `Str::headline($key)` while every OTHER producer of a display label — beam's default
     * summary provider among them — read `$nav->label` bare and rendered an empty string for a resource
     * that never declared one. One method, one fallback, every caller.
     */
    public function resolvedLabel(): string
    {
        return $this->nav->label !== '' ? $this->nav->label : Str::headline($this->key);
    }

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
     * @param  class-string|null  $filterProvider
     * @param  class-string|null  $summaryProvider
     * @param  class-string<Data>|null  $createResultData
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
        ?string $singularLabel = null,
        ?string $filterProvider = null,
        ?string $summaryProvider = null,
        // NavMetadata overlay — each rebuilds the nav field-by-field:
        ?string $label = null,
        ?string $group = null,
        ?string $icon = null,
        ?string $section = null,
        ?int $navOrder = null,
        ?string $routeName = null,
        ?string $createResultData = null,
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
            singularLabel: $singularLabel ?? $this->singularLabel,
            filterProvider: $filterProvider ?? $this->filterProvider,
            summaryProvider: $summaryProvider ?? $this->summaryProvider,
            createResultData: $createResultData ?? $this->createResultData,
        );
    }
}
