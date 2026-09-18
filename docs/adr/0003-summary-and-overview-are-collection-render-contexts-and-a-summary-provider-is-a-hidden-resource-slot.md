# ADR-0003 — `summary` and `overview` are COLLECTION render contexts, and a summary provider is a hidden resource slot

**Status:** accepted
**Date:** 2026-09-14
**Repo:** `schemastud/laravel-frame`
**Wayfinding:** `.scratch/splicewire/splicewire-ecosystem/realm-dashboards/` PRD ("The axis question, answered",
"Implementation Decisions"), tickets 01, 02, 03.
**Extends:** ADR-0001 (a field on frame's contract must be read by frame), ADR-0002 (the manifest carries no `model`).
**Relates to:** `splicewire/laravel-beam docs/adr/0222-a-realm-dashboard-is-one-read-only-resource-per-realm-of-card-rows.md`
(the consumer of both halves of this decision).

## Context

Frame's render-context enum was closed at five and validated: `edit`, `detail`, `list-column`, `list-item`,
`row-cell` (measured 2026-09-14 at `2783dec`, `WidgetContextProjector.php:22`). Every context but one takes a
**property** as its subject; `list-item` takes a **record** and is therefore class-level only. Nothing took the
**collection** as its subject, so a resource had no way to say how it looks compressed (a row of figures) or
expanded (a card with a body). Every dashboard in the estate was hand-built for that reason.

The map's first question was whether a "dashboard" is a third axis. It is not. Two axes already exist and were
ruled independent twice: **realm** (beam; server-side, with a principal in hand; selects which cards exist in
a projection) and **render context** (frame; wire + client, resolved by name; selects which rendering of a node
or record). `splicewire/splicewire-app docs/adr/0176-the-threads-substrate-is-mode-invariant-mode-and-realm-are-render-pivots-over-one-message-series-not-storage-forks.md`
ruled mode and realm render pivots, not storage forks; `.scratch/splicewire/laravel-beam/realm-and-floor-reconciliation/tickets/04-realm-membership-has-three-readers.md`
ruled that frame stays realm-blind. Realm changes data reach and gating; context changes shape. They do not
merge, so frame gains contexts and no notion of realm.

The template for supplying the figures already existed: `ResourceDefinition::$filterProvider` is a hidden,
nullable class-string slot, never on the wire, preserved by `withOverrides()`, container-made after the
access gate (`ResourceDefinition.php:78-80` at `2783dec`).

## Decision

1. **Two new class-level contexts, `summary` and `overview`, join the closed enum.** The class-only constant
   splits by subject grain: `RecordContexts = ['list-item']`, `CollectionContexts = ['summary', 'overview']`.
   Declaring either on a property is rejected, as `list-item` already is. "Summary of THIS record" on a
   detail page is `list-item`; no record-level summary context is added.
2. **The cascade gains one edge, `overview ← summary`**, hardcoded on both sides beside `row-cell ← edit`
   (`ContextManifest.php` `'inherits' => ['row-cell' => ['edit'], 'overview' => ['summary']]`;
   `@schemastud/frame` `INHERITS`). The client resolver stamps `x-frame-context` onto the schema it hands
   the seam registry, so a context-default widget can fire on a predicate; a declared widget name still
   wins. An opted-out parent (`participates: false`) lends nothing. No tier field joins the resolved shape.
3. **The manifest's root-node merge is per-pointer with reflection winning**, so a contributor returning the
   root pointer can no longer clobber the reflected class-level entries.
4. **A summary provider is a hidden resource slot mirroring the filter provider**:
   `ResourceDefinition::$summaryProvider` (`class-string<Contracts\ResourceSummaryProvider>|null`), never on
   the wire, never in generated TypeScript, preserved by `withOverrides()`. Frame mounts
   `resources/{resource}/summary` beside the filters routes under the resource root, gated by the same
   resource-access authorizer; it answers `SummaryResponseData {key, label, icon, figures[], overview?}` with
   `SummaryFigureData {key, label, value, tone?}` and `OverviewData {headline?, items[], period?, note?}`,
   emitted through `#[ResponseFromData]` on the controller method. **No `href`**: the socket is realm-blind,
   and an href is stamped by whichever realm-aware backing consumes the summary. No provider, or a provider
   that declines, answers **404**, exactly as `filters/options` does.
5. **No fourth declaration site.** The summary endpoint is a non-particle surface and takes the
   `#[ResponseFromData]` trio; `OverviewData` is a declared Data class, not an open bag (the ticket 02 review
   replaced `mixed`).

## Consequences

- **The list shell's cards gate is "participates AND resolvable".** The shell renders rows as cards only
  when the resource's root `list-item` entry participates and its named widget resolves in the registry.
  Tower already carried unbound `list-item` declarations, so participation alone would have flipped its
  lists into a grid of nothing. A misdeclared name reports an **honest unbound** (`unbound: true`) rather
  than a silent fallback.
- **Links go through an injected `CardLinkRenderer`.** Frame does not know the host's router; a card's href
  is drawn by whatever the host injects (beam-inertia injects Inertia's `Link`).
- **BC on the wire is additive.** `known` grows by two names and `inherits` by one edge; a consumer with an
  exhaustive switch over `FrameContext` must add two arms.
- **The provider slot is frame's contract by ADR-0001's test** because frame reads it (the route
  container-makes it). Beam's default — count through the scoped index query — is beam's decision and is
  recorded in the related beam ADR.
- **Promoted to fleet tier 2026-09-18** after three hosts (beam, satellite and tower starters) proved a
  dashboard config-only: `rushing/splicewire-beam-runbook docs/adr/0005-a-realm-dashboard-is-one-read-only-resource-per-realm-of-card-rows.md`
  (lifted from `splicewire/laravel-beam docs/adr/0222-…`, citing this ADR for the contexts). This record
  stays the repo-local original per `docs/conventions/adr-placement.md`.

## Records

- Commits: laravel-frame `c3979f8`, `3d3ac4b` (ticket 01), `3bbc3d4`, `3cb23da` (ticket 02), `8759045`
  (tone constants on `SummaryFigureData`); `~/Workspaces/js/packages/schemastud` `9140376`, `f12abcb` (01),
  `97a6bca`, `a95d932` (03), `965dd6e` (README), `13d0a50` (`record-line`, single-page pager).
- Map: `~/Workspaces/splicewire-ecosystem/.scratch/splicewire/splicewire-ecosystem/realm-dashboards/`.
