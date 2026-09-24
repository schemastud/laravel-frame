# ADR-0004 — The manifest carries no PHP class-string: `editData` names its generated type; `query` and `policy` are server-side inputs

**Status:** accepted
**Date:** 2026-09-24
**Repo:** `schemastud/laravel-frame`
**Wayfinding:** `.scratch/splicewire/laravel-beam/fqcn-in-resource-descriptors/FINDINGS.md`
**Extends:** ADR-0001 (a field on frame's contract must be read by frame), ADR-0002 (the manifest names
the generated type and carries no `model`). This decides what ADR-0002 §Consequences left nominated.

## Context

ADR-0002 moved `data` to the generated type's dot-form name and hid `model`. `createResultData`
(`a2c2f17`, 2026-09-22) took the same transformer. Three slots still ship as declared, so one entry
mixes `Splicewire.Beam.Data.HookData` with `Splicewire\Beam\Data\HookInputData`.

Measured 2026-09-24 against the booted registry (`ResourceRegistry::all()`, `json_encode` of each
definition, string leaves containing a backslash). Unit: resources per host.

| host | definitions | `editData` | `query` | `policy` |
|---|---|---|---|---|
| `splicewire/splicewire-app` | 47 | 14 | 16 | 1 |
| `laravel-tower-starter` | 46 | 13 | 16 | 1 |
| `laravel-beam-starter`, `laravel-satellite-starter` | 18 each | 6 each | 0 | 1 each |

The client readers across every host UI, starter and JS package are:

- **`editData`:** zero wire readers. The one hit, `splicewire-app/ui/src/_prototype/admin-redesign/ar02-…tsx:250`,
  reads a local fixture, not the manifest. The edit form's schema comes from
  `GET /frame/resources/{key}/schema`, which reflects `editData ?? data` server-side.
- **`query`:** zero readers. It is a data-filters query-builder class. It is not a Data class, so no
  generated type exists to name, and the filter panel reads `…/filters`.
- **`policy`:** one reader, `splicewire-app/ui/src/features/operator/resources/OperatorResourcesPage.tsx:93`.
  It shows the `resources` area's own gate name as a badge.

Also, the generated response schema still listed `model` as a **required** property. The JSON had not
carried `model` since ADR-0002, so the schema was out of step with the wire.

## Decision

1. **`editData` is a Data-class slot and takes `GeneratedTypeName`,** like `data` and `createResultData`.
   The PHP property stays the class-string that the schema endpoint reflects.
2. **`query` and `policy` are server-side inputs.** Each takes `#[Hidden]` (laravel-data),
   typescript-transformer's `#[Hidden]` and `Keyword(Keywords::Hidden)`. This is the triple that
   `filterProvider` and `summaryProvider` already carry. The PHP properties are unchanged:
   - `ResourceFilterDefinition` and `ResourceFilters` read `query`.
   - `PolicyWriteGate`, `ResourceVisibility` and `ResourceRegistryBacking` read `policy`.
   `policy` is hidden as a **slot**, not filtered by value. A Gate ability is as much a gate input as a
   policy class, and the client's authority is the injected `can()` plus the per-actor `can` on the
   ContextManifest.
3. **`model` gains `Keyword(Keywords::Hidden)`,** so the response schema stops advertising it.

The rule this states: **a `ResourceDefinition` slot holding a class-string either names a Data class,
projected by `GeneratedTypeName`, or is a server-side input, hidden from JSON, TS and schema.** There
is no third spelling.

## Consequences

- **BC on the wire, not on the constructor.** No `new ResourceDefinition(...)` or `withOverrides(...)`
  call site changes.
- **Consumers that change together:**
  - `@schemastud/frame`'s hand-written `AdminResourceDefinition` drops `query` and `policy`.
  - The flagship's `OperatorResourcesPage` reads its badge from its own `resources` registry row
    (`intent.policy`), a surface that exists to show a declaration's gate to an operator.
  - The flagship's `FrameManifestTest` expects the dot-form `editData`.
- **A dotted `editData` name need not resolve on every host.** At the flagship, 4 of 14 `editData`
  classes are in the emitted ambient tree. The other 10 carry no `#[TypeScript]` and are not particle
  declarations, so the host's transformer skips them. The name is informational, as `data` is
  (ADR-0002). Whether write DTOs should be emitted is `DeclaredParticleTypes`' "unexported" bucket, not
  this ADR.
- **Gitignored generated mirrors** (`laravel-beam-market-starter`, `laravel-beam-commerce-starter`
  `resources/js/generated/Schemastud/Frame/Registry/index.ts`) drop `query`/`policy` on their next
  `typescript:transform`. Nothing is committed.
- `GeneratedTypeName` stays a plain backslash-to-dot mapping. It ignores `#[TypeScript(name:, location:)]`,
  which beam's `DeclaredParticleTypes::emittedName()` honours. Zero such overrides exist in the estate
  today, so the two agree. Frame cannot call beam (lower tier), so the divergence stays a named risk,
  not a merge.
