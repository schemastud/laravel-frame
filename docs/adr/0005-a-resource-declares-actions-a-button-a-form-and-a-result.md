# ADR-0005 — A resource declares ACTIONS: a button, a form from a declared input, and a declared result

**Status:** accepted — decided on owner delegation 2026-09-25; revisable
**Date:** 2026-09-25
**Repo:** `schemastud/laravel-frame`
**Wayfinding:** `.scratch/splicewire/laravel-beam/particle-operation-surface/` ticket 21 ("Frame renders a
declared operation"), spawned by `.scratch/splicewire/splicewire-ecosystem/extensions-live/` ticket 03.
**Extends:** ADR-0001 (a field on frame's contract must be read by frame), ADR-0004 (the manifest carries no
PHP class-string).
**Relates to:** `splicewire/laravel-beam docs/adr/0223-a-particle-op-opts-into-a-frame-action-with-affordance.md`
(the producer that projects its operations onto this concept).

## Context

Frame renders a resource's list, create form and detail from its manifest entry, with no host page and no JS
build. It had no way to render any other write. `RouteMounts` is a closed five-case verb, the only row action
frame draws is `delete`, and `ResourceDefinition` carried no field describing anything beyond CRUD (measured
2026-09-25, ticket 21's premises). So a package that seats its own screen through frame recast every non-CRUD
write as a resource **create**: `splicewire/laravel-beam-commerce` declared a second resource over the same
ledger rows, a `FrameResourceHandler` whose `store()` reloaded credit, and a create form titled from the
operation's input DTO.

The producer already declares everything a button needs — its operations carry an input Data class, an
ability, a subject and a mount. What was missing was a frame concept to carry it, and it must not name beam
(frame is the lower tier, ADR-0001) or ship a PHP class-string (ADR-0004).

## Decision

1. **`ResourceDefinition` gains `actions: list<ResourceActionDefinition>`**, default `[]`. An action is
   generic and names no producer:

   | field | meaning |
   |---|---|
   | `key` | unique within the resource; the `can.actions` key and the schema endpoint segment |
   | `label` | the button's words |
   | `scope` | `resource` (beside the list's "New") or `record` (a row action and a detail-page action) |
   | `method`, `url` | the request frame sends; a `record` URL template carries `{id}` |
   | `input` | the Data class the body must satisfy, or null for a **confirm-only** action |
   | `result` | `toast` (the response's message, then refetch the resource; the default) or `navigate` (open the resulting record: the response's `data.id`, else the acted-on record) |
   | `destructive` | the button reads as dangerous, and a confirm-only press asks first |

   `input` is a Data-class slot under ADR-0004's rule: a class-string in PHP, the generated type's dot-form
   name on the wire (`GeneratedTypeName`). Nothing else on an action holds a class.

2. **The form is fetched, not named.** `GET {prefix}/resources/{resource}/actions/{action}/schema` reflects
   `input` through the host's configured generator chain in request mode, exactly as
   `resources/{resource}/schema` reflects `editData ?? data`. So the action form uses the keys the action's
   URL accepts (`#[MapInputName]` included). A confirm-only or unknown action answers 404. The endpoint is
   behind the same resource-access gate as every other read; seeing a form grants nothing.

3. **The actions ride the shell's `ContextManifest` block**, as `layout`, `createAffordance` and
   `singularLabel` do: a shell is handed its block and never the definition. The block gains `actions` (the
   list) only when the resource declares any, so every existing block is byte-identical.

4. **Visibility is per actor, answered by the producer, and advisory.** The block's `can` gains
   `actions: {key: bool}`, from `ResourceAuthorizer::actionCapabilities()`, which asks a new port,
   `Contracts\ResourceActionAuthorizer`. Frame cannot know the rule behind a producer's URL, so its default
   binding (`DenyingResourceActionAuthorizer`) answers **false**: a host that projects actions and binds no
   answer gets no buttons, never buttons that 403. A `record` action is asked at class level; the mount is
   the enforcement and refuses per row. A button is drawn only where `can.actions[key]` is `true`.

5. **Frame does not invoke through its own socket.** The action's URL is the producer's mount, and it stays
   the enforcement. `@schemastud/frame`'s transport gains optional `invoke` and `getActionSchema` methods, and
   `createResourceTransport` supplies them when the host's HTTP adapter can write.

## Scope

- **Covered:** resource-scope and record-scope actions, a form from a declared input, confirm-only actions,
  toast-and-refresh and navigate results, per-actor visibility.
- **Not covered:** an action addressing a record through a parent edge (beam's `ParentSubject`). Neither
  placement above fits it and frame has no edge concept; a producer does not project one (beam ADR-0223).
- `RouteMounts` is unchanged. An action is not a route leaf; it is a control on the list and detail leaves.

## Consequences

- **BC is additive on the constructor and on the wire.** `actions` is the last constructor parameter with a
  default, `withOverrides(actions:)` replaces the list, and a resource without actions emits no new key.
- **The TS mirror is hand-written** (ADR-0002): `@schemastud/frame`'s `AdminResourceDefinition.actions`,
  `ContextManifest.actions` / `can.actions` and `ResourceActionDefinition` change in lockstep with this.
- **Host transports that predate this render no action buttons** until they supply `invoke` (directly or by
  passing a writing HTTP adapter to `createResourceTransport`). The buttons are hidden, not broken.
- A host's own `Toolbar` or `RowActions` slot does not hide actions: frame renders them beside those slots,
  because an action is a declaration and a slot is presentation.
