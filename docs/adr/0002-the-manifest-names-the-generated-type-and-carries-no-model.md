# ADR-0002 — The manifest wire names a resource's GENERATED type, and carries no `model`

**Status:** accepted
**Date:** 2026-09-12
**Repo:** `schemastud/laravel-frame`
**Wayfinding:** `.scratch/splicewire/laravel-beam/composite-backing/` ticket 01 (PRD "the manifest stops naming models")
**Extends:** ADR-0001 (a field on frame's contract must be read by frame).
**Relates to:** `splicewire/laravel-beam docs/adr/0212-a-resource-declares-what-backs-it-not-how-it-is-shaped.md`.

## Context

`GET /frame/manifest` serialized `ResourceDefinition` as declared, so every entry shipped two PHP
class-strings to the browser: a nullable `model` (an Eloquent class) and `data` (the list-row Data
class). ADR-0001 kept `model` "for now" through the plug-seam clause and named the class-string leak as
narrowing-but-not-closed. Two things changed since:

- **Frame itself now reads `model`.** `ResourceAuthorizer::allows()` resolves the write-gate policy
  subject from `$definition->model` (commit 1284c89). The field passes ADR-0001's test directly, not
  through the seam — so it is not going anywhere on the PHP side.
- **Nothing on the wire reads it.** Measured 2026-09-12 with two differently-shaped probes across every
  host UI, starter and JS package (`~/Herd/*/ui`, `~/Herd/*/resources/js`, `~/Workspaces/laravel/starters`,
  `~/Workspaces/js/packages`; rc=0, stderr empty): zero manifest-entry readers of `.model`. The one JS
  hit (`~/Herd/schemastud` `Pages/Beam.tsx:142`) reads a host-shaped Inertia prop the host's own PHP
  builds from the server-side field, not this manifest. `@schemastud/frame`'s `AdminResourceDefinition`
  declared both fields and read neither.

Beam's ADR-0212 says a resource declares *what backs it*, and a backing need not be a model. A wire
that says `model: App\Models\Silo` on one entry and `model: null` on the next re-asserts the
model-or-nothing framing that ADR removed, for a consumer with no use for either answer.

## Decision

1. **`model` is a server-side input, never a wire field.** `ResourceDefinition::$model` stays a
   constructor parameter and a public property (frame's write gate and the `FrameResourceHandler` plug
   seam read it) and carries `#[Hidden]` (spatie/laravel-data — dropped from `toArray()`/JSON) and
   typescript-transformer's `#[Hidden]` (dropped from the emitted type). `@schemastud/frame`'s
   `AdminResourceDefinition` drops the field.

2. **`data` on the wire is the generated type's name in dot form** — the class's real native namespace
   with dots for backslashes (`Schemastud.Frame.Tests.Fixtures.SampleResourceData`) — produced by the
   `GeneratedTypeName` transformer at transform time. The PHP property remains the class-string:
   `FrameManifestController` and the schema endpoint reflect it, and a producer's `withOverrides(data:)`
   still takes a class.

**Why the type name and not a schema reference.** Three reasons, in order of weight:

- It is the identifier the consumer already holds. `typescript:transform` emits the row DTO under
  exactly that name, so the manifest names a thing that exists on the client; a schema reference names
  a URL the client would have to fetch.
- Frame's per-resource schema (`GET /frame/resources/{key}/schema`) is the **edit** shape
  (`editData ?? data`), so a reference to it would describe a different thing than the list rows this
  field is about.
- The naming rule is already settled one rung up and is not frame's to restate: beam's particle doctrine
  ("type names are the class's real native namespace, dots for backslashes", no `App.Data` rebasing).
  The transformer is a projection of that rule, computed with no I/O and no second registry.

The rejected alternative — a separate wire DTO beside `ResourceDefinition` — would give the manifest
two truths for one entry and break the "the frontend type IS the backend projection" premise the class
docblock states; the hidden/transformer pair keeps one class and changes only its wire.

## Consequences

- **BC on the wire, not on the constructor.** Every `new ResourceDefinition(model: …, data: …)` call
  site (beam's `toResourceDefinition()`, tower, beam-accounts, the test fixtures) is untouched. A
  consumer that read `entry.model` from `/frame/manifest` would break; measured readers: zero. A
  consumer that compared `entry.data` to a PHP class-string would break; the two assertions that did
  (`laravel-frame tests/ManifestTest.php`, the flagship's `FrameReviewQueueTest`) were flipped in the
  same change.
- **The class-string leak ADR-0001 named narrows again but is still not closed.** `editData`, `query`
  and `policy` still ship as declared. `editData` is the same kind as `data` and the same one-attribute
  fix applies; it is deliberately **not** done here because the ticket that decided this scoped it to
  `model`/`data`, and its single measured reader
  (`splicewire-app/ui/src/_prototype/admin-redesign/ar02-…tsx:250`) splits the value on a backslash —
  a prototype that would need the same flip. Nominated, not decided. `policy` is an ability key, not a
  class; `query` is ADR-0001's named non-finding and stays there.
- **A host that hand-projects the definition** (as `~/Herd/schemastud`'s `BeamController` does with
  `'model' => $def->model`) still can: the PHP field is intact. That such a projection ships a class
  name is the host's choice, outside this contract.
- **The TS mirror is hand-written, not generated.** No host's generated artifacts carry
  `Schemastud.Frame.Registry.ResourceDefinition` (rg over every host's `resources/js`/`ui/src` and the
  starters, rc=1, stderr empty), so `@schemastud/frame/src/types.ts` is the only place the client type
  lives and must be edited in lockstep by hand, as it was here.
