# ADR-0010 — Render contexts are registry entries

**Status:** accepted — frame-foundation decision 86 (auto-grill, 2026-11-20); built by ticket 90
**Date:** 2026-11-22
**Repo:** `schemastud/laravel-frame`
**Wayfinding:** `.scratch/splicewire/splicewire-ecosystem/frame-foundation/` tickets 86 (the ruling) and 90.
**Relates to:** ADR-0001 (frame owns presentation contracts and reads every field it declares), ADR-0003 (whose
decision 2, the cascade "hardcoded on both sides", this supersedes), ADR-0007 (widget names are checked against a
host-committed vocabulary), ADR-0008 (the resource registry port is not an extension point).

## Context

Frame's render contexts were a closed list: three constants on `WidgetContextProjector` (`KnownContexts`,
`RecordContexts`, `CollectionContexts`), an `inherits` array literal in `ContextManifest`, and a string comparison
that admitted the class-level `#[RowActions]` case. A package could not add a context, and the server and client each
held their own copy. Decision 86 opened the set.

## Decision

1. **Registry.** `Schemastud\Frame\Registry\WidgetContextRegistry`, `#[IsRegistry(root: 'frame.contexts',
   entryType: WidgetContext::class, onKeyDuplicate: Supersede)]`. An entry is
   `WidgetContext {name, grain: ContextGrain, fallback: list<string>, reserved: list<string>}`, keyed
   `frame.contexts.<name>`; a name is one `Key::SEGMENT_PATTERN` segment.
2. **Policy: `Supersede` for every entry, frame's seven included** (86 item 2). A replacement keeps the displaced
   entry's slot, so `known` keeps its order, and it must keep its grain.
3. **Construction** (86 items 3 and 4). The registry seeds frame's seven in its own constructor, as
   `schemastud/laravel-frame`, in the wire order `known` has always had. Frame's provider binds it as a singleton
   and hands it to `WidgetContextProjector` by nullable constructor argument (null is a registry of its own). The
   block builder reads `known` and `inherits` through the projector 89 hands it; `ContextManifest` gains no
   argument. A package or host registers in its provider's `boot()`, never `register()`.
4. **Vocabulary** (86 item 5). The vocabulary file gains an optional `inherits` key, written only when a kit's edges
   differ from frame's two; absent means frame's two (`WidgetVocabulary::inherits()`). `reserved` stays the flat
   union and `version` stays 1. `known` and `inherits` remain unread wire keys (decision 05); their reason now names
   this registry.
5. **Throws** (86 item 7). Projection throws as before, with the class-level reserved-name check read from the
   entry. Registration throws `InvalidArgumentException` on a bad name, a self fallback, any fallback on `edit`, a
   grain change (naming both registrants) and `reserved` on a non-property grain. One check after boot (an
   `$app->booted()` pass in frame's provider, or the projector's first read where there is no container) throws on a
   fallback to an unregistered context or to one of a different grain. The doctor's advisories are beam's (ticket 92).

## Consequences

- `frame.contexts` **is** an extension point, unlike the resource registry port of ADR-0008: a package may add a
  context, or supersede one of frame's, and frame projects it.
- A host that registers nothing serves a byte-identical manifest.
- The three projector constants are removed. Their one reader outside frame, beam's `FramedDeclarationAudit`, moves to
  the registry in a companion beam commit landed with this one.
- When two packages supersede the same frame context, the later registration wins; the doctor names both (92).
- A context registered in a provider's `register()` can be lost to a later binding; the remedy is the documented
  `boot()` rule, not a runtime guard.
