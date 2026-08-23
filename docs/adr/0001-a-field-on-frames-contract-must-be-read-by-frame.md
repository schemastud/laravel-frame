# ADR-0001 — A field on frame's contract must be read by frame, or by frame's own plug seam

**Status:** accepted
**Date:** 2026-08-23
**Repo:** `schemastud/laravel-frame`
**Wayfinding:** `.scratch/splicewire/laravel-beam/particle-contribution-seam/` tickets 06, 10, 01, 13
**Relates to:** `splicewire/laravel-beam` ADR-0212 (a resource declares what backs it),
`splicewire/splicewire-app` ADR-0092 (beam re-scoped to the splicewire vendor).

## Context

`Schemastud\Frame\Registry\ResourceDefinition` is frame's published description of an admin resource.
It accumulated three fields describing how a resource is **backed** — `sourceKind` (`'model'|'service'`),
`model` (an Eloquent class-string) and `source` (a `UnionSource` class-string) — plus the `UnionSource`
port itself and `resolveSource()`.

Ticket 10 opened expecting to WIDEN frame's contract so it could express a resource that is both
model-backed and custom-read. Measurement inverted the question:

- **Frame's own PHP reads none of the three.** The sole hit across `src/` was a docblock.
- **No `.ts` in the workspace mentions `sourceKind`**, and frame's TS mirror never carried it.
- Every consumer of those fields is a `splicewire/laravel-beam` consumer.

They were beam's contract living in a schemastud class. The move was a **narrowing**, not a widening —
which cuts the opposite way on the vendor seam.

## Decision

**A field belongs on frame's `ResourceDefinition` only if frame itself reads it, or if frame's own
published plug seam (`Contracts\FrameResourceHandler`) invites a host to read it.**

Applying that test:

| field | verdict |
|---|---|
| `sourceKind` | **removed** — zero readers anywhere, including frame |
| `source` + `resolveSource()` + `UnionSource`/`UnionQuery`/`ResolvedUnionItem` | **removed** — beam's port; frame owns no list/record runtime |
| `model` | **kept, for now** — read by three hosts through frame's own plug seam, whose docblock invites exactly that |
| `key`, `data`, `nav`, `form`, `layout`, the affordance flags | **kept** — frame's own presentation contract |

`sourceKind`'s removal resolves a live homonym for free: `Splicewire\Beam\Source\SourceKind` is an
unrelated enum in the same domain meaning *who owns the content behind a `$ref`*, and tower's
`OpenApiSpecData::$sourceKind` is a third, unrelated sense.

The union port could not be converted in place: frame is the lower tier and may not name beam, so
`UnionSourceTest` and `FixtureUnionSource` are **deleted** rather than migrated. The contract they
exercised now lives in beam as `StreamsRecords` / `ResolvesRecord`.

## Consequences

- **BC break on a published constructor.** `sourceKind` was required positional #2. ⚠️ Its production
  call sites are **four**, all inside this estate — beam's `toResourceDefinition()`, tower ×3,
  beam-accounts ×1. The "7 of 7 consumers" figure carried into the execution ticket counted *repos with
  frame in their lock*, which is a different measurement and overstated the blast radius ~2×.
- **`model` is left as a known exception to this ADR's own test**, deliberately. It passes the test only
  through the plug-seam clause, and ⚠️ **frame itself does not require beam** (zero `Splicewire\` code in
  `src/`), so "all frame consumers carry beam" is a contingent fact about today's estate rather than a
  structural guarantee. Narrowing it out remains ticket 10's.
- ⚠️ **`query:` is the same defect and this test has never been run against it.**
  `ResourceDefinition::$query`'s own docblock calls it *"data-filters query class"* — a
  `rushing/laravel-data-filters` concept living in a schemastud class, exactly the shape that sent
  `model`/`source`/`sourceKind` home. Recorded here as a **named non-finding**: whether two registries in
  different vendors should merge is a registry-kernel question, not this map's. Its word-collision tally
  stands at 13.
- **The `/frame/manifest` class-string leak narrows but does not close.** Dropping `source` stops one of
  the three; `data`/`editData`/`query`/`policy` still ship PHP class-strings to the browser.
