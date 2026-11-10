# ADR-0006 — The form control keyword is `x-widget`, and an ordinary edit entry binds it

**Status:** accepted — ruled 2026-11-10 (frame-foundation ticket 01, ruling 1 and ruling 3, amended once after the Codex
review); built by frame-foundation ticket 08 (build slice 2)
**Date:** 2026-11-10
**Repo:** `schemastud/laravel-frame`
**Wayfinding:** `.scratch/splicewire/splicewire-ecosystem/frame-foundation/` tickets 01 and 08; CONFORM rows 01-1, 01-8, 01-12.
**Supersedes in part:** `splicewire/splicewire-app docs/adr/0081-frame-v1-contract-shells-manifest-editable-shape.md`, the
keyword clause of its Decision bullet "`x-stud-*` v1 = exactly one keyword, `x-stud-widget`": the keyword's name, the
tier prefix as it applies to the widget keyword, and the derivation `#[Widget('textarea')]` → `x-stud-widget`. The rest
of that bullet stands.
**Relates to:** ADR-0001 (a field on frame's contract must be read by frame).

## Context

ADR-0081 minted `x-stud-widget` (and `x-stud-widget-options`) as frame's one widget-selection keyword, prefixed to the
schemastud tier. Seam's form never read it: seam resolves the bare `x-widget`, so `@schemastud/frame` carried a bridge
(`bridgeHostWidgets`) that copied the namespaced keyword onto the bare one, and a strip (`stripHostWidgets`) that removed
it again in `bare` mode. Two spellings of one fact, joined by client code, meant a widget declared on a Data class could
be discarded by a default and a misspelled name rendered as a plausible control (ticket 01, §Why).

Separately, `#[WidgetIn('edit', '<name>', …)]` let an author name an edit-context widget that never reached the form:
the form builds its controls from the node keyword and reads no manifest, so an ordinary edit entry was inert unless the
same name was repeated with `#[Widget]`. Two declarations of one control could disagree.

## Decision

1. **The form control is `x-widget`, with `x-widget-options`.** `Keywords::Widget` and `Keywords::WidgetOptions` take
   the bare values and stay in `Keywords::owned()`. `x-stud-widget` and `x-stud-widget-options` are gone: no alias, no
   dual spelling, no deprecation window for a first-party caller (the map's greenfield rule). A caller this ticket does
   not update is named in the frame-foundation CONFORM ledger.
2. **An ordinary edit entry binds the form control.** When the schema is generated, `WidgetContextsStrategy` projects a
   property's `#[WidgetIn('edit', '<name>', options)]` that is not `heavyweight` onto that property's `x-widget` and
   `x-widget-options`, on any class a form is drawn from. The entry and the keyword are one declaration and cannot
   disagree, so the client needs no precedence between them.
   - A **heavyweight** edit entry is not projected. It names a full-surface editor, not a form control.
   - A property carrying **both** `#[Widget]` and an ordinary edit entry is **refused at projection**
     (`WidgetContextProjector::forProperty`, an `InvalidArgumentException`, as the projector's other declaration
     errors are). A heavyweight entry beside `#[Widget]` is not a second form control and is allowed.
   - The edit entry also stays in `x-stud-widget-contexts`, unchanged.
3. **`x-stud-widget-contexts` stays**, name and emission unchanged (ticket 01, ruling 6). Its one reader is server-side:
   laravel-beam's `CollectionResourceContextNodes`. A client reads the manifest's `byNode`, never this keyword; the
   manifest controller's docblock says so.
4. **Unchanged from ADR-0081:** the keyword is embedded in the one form schema, not a sidecar `uiSchema`; there is no
   `x-stud-form` keyword; the RFC 6648 caveat still covers the `x-` keywords that remain (`x-stud-resource-ref`,
   `x-stud-widget-contexts`). `x-stud-resource-ref` is untouched.

## Consequences

- PHP emits the keyword seam already resolves, so today's JS draws `#[Widget('textarea')]` and an ordinary edit entry in
  either form mode without a bridge (expand). Removing the bridge and the strip from `@schemastud/frame` is a later
  slice (ticket 01, build slice 4: contract), so the JS removal never meets an old producer.
- `bare` suppresses a projected edit entry like any `x-widget`, because it reaches the form only as that keyword; the
  projection happens on the server, `bare` acts on the client (ticket 01, rulings 2 and 3).
- A schema stored before this change (a database row, a committed generated file) still carries `x-stud-widget` and
  loses its hint once the bridge goes; regenerate it. No database was read.
- An authored schema, which no projector touches, writes the form control as `x-widget` itself.
