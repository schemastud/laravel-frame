# Frame attributes

Declare a resource with Beam's `#[ParticleResource]` and give it a label to present it in Frame.
Beam projects that declaration through `ParticleResourceRegistryAdapter`; hosts do not need a
second producer. All nine attributes below live in `Schemastud\Frame\Attributes`.

## Targets, contexts and wire output

Property contexts are `edit`, `detail`, `list-column` and `row-cell`. `list-item` describes one
record; `summary` and `overview` describe the collection and belong on the class. A class-level
`row-actions` list column is the one property-context exception.

| Attribute | Target and context | Wire output | Advisory doctor check |
| --- | --- | --- | --- |
| `#[Widget('textarea', options: [...])]` | Property; form control (`edit`); primitive read nodes also resolve in `detail` | `x-widget`, and `x-widget-options` when supplied | `frame.widget-name`; `frame.widget-node-type` for stock form controls |
| `#[WidgetIn('detail', 'badge')]` | Repeatable on properties or classes, at the grain described above | Context entry in the manifest's `byNode` and the property's `x-stud-widget-contexts`; an ordinary named `edit` entry also emits `x-widget` / `x-widget-options` | `frame.widget-name`; `frame.widget-node-type` for a projected stock form control; `frame.projection` for invalid context or grain |
| `#[Column('badge', label: 'Status')]` | Property; `list-column` | `WidgetIn` entry with participation, optional widget, label, sort and options; `filterable: true` joins the options bag | `frame.widget-name`; `frame.projection` |
| `#[NotInList]` | Property; `list-column` | Context entry with `participates: false` | `frame.projection`; no named widget to check |
| `#[RowActions(['edit', 'delete'])]` | Class; root `list-column` | Entry with widget `row-actions` and `options.actions` | `frame.widget-name` checks reserved names outside their allowed root context; `frame.projection` |
| `#[Summary('stat-row')]` | Class; collection `summary` | Root context entry; `false` opts out and an omitted name keeps the default | `frame.widget-name`; `frame.projection` |
| `#[Overview('figure-card')]` | Class; collection `overview` | Root context entry; inherits `summary` unless narrowed with `inheritsBinding`; `false` opts out | `frame.widget-name`; `frame.projection` |
| `#[ReadOnlyField]` | Property; edit form | Standard JSON Schema `readOnly: true`; property stays visible and disabled | `frame.projection` covers schema generation; no widget-name check of its own |
| `#[ResourceRef('plans', value: 'id', label: 'name')]` | Property; resource picker in the edit form | `x-stud-resource-ref` with `resource`, `value`, `label`, `multiple` and optional `scope` | `frame.projection` covers schema generation; no reference-target check is supplied by these IDs |

A context entry uses `participates`, and emits only the supplied `widget`, `options`, `sort`,
`label`, narrowed `inheritsBinding` and true `heavyweight` fields. The manifest groups entries as
`byNode[property][context]`; class entries use the empty root pointer. Property schema context
metadata remains available to Beam's server-side Collection contributor; clients read `byNode`.
The context block also declares the `row-cell` ← `edit` and `overview` ← `summary` inheritance edges.

Use either `#[Widget]` or an ordinary named `#[WidgetIn('edit', ...)]` on a property. Declaring both
throws during projection. A heavyweight edit entry names a full-surface editor and does not emit
the form-control keyword. An unknown context or a context on the wrong grain also throws.
An unknown widget name never throws in PHP: the doctor reports it, and an unresolved runtime name
draws the visible unbound marker. `enriched` is the default form mode and keeps declared widgets;
`bare` deliberately selects plain controls. Invalid form modes are refused by `FormMode`.

## The host vocabulary

Commit `resources/frame/vocabulary.json` in the host. The command and `frame-vocabulary` bin
arrive with ticket 33 and are not yet shipped; the starter's script is ticket 39. Until then,
a host writes the file by hand or has none, in which case the doctor reports name checks as
inconclusive. Once the tooling ships, generate the file from the widget registry the host's kit
builds, including its custom widget registrations:

```bash
pnpm frame:vocabulary --write
```

The planned command invokes Frame's `frame-vocabulary` tool. With several kits, the file records their
union and any conflicting bindings. PHP reads the file through `WidgetVocabulary`; change its
location with `config('frame.vocabulary')`. Regenerate when registrations change. A stale file
can miss a runtime problem; a missing or incompatible file makes name checks inconclusive.

## Advisory doctor IDs

Beam owns the doctor integration; laravel-frame supplies the vocabulary reader and declaration
walk. The four `FramedDeclarationAudit` IDs below run today and are advisory (`gate: false`),
including when a finding has fail severity. `frame.wire-keys` arrives with ticket 40 and has not
yet shipped; it is also specified as advisory:

| ID | Owner under `Splicewire\Beam\Doctor` | What it checks |
| --- | --- | --- |
| `frame.vocabulary` | `FramedDeclarationAudit` | File readability/version, context and form-mode sets, and conflicting kit bindings. Remedy once ticket 33 ships: `pnpm frame:vocabulary --write`; until then, write the file by hand. |
| `frame.widget-name` | `FramedDeclarationAudit` | A declared name is bound in its context; reserved names are used only in their allowed context. |
| `frame.widget-node-type` | `FramedDeclarationAudit` | A stock form-control binding supports the known schema node type; unknown types and conflicting bindings are skipped. |
| `frame.projection` | `FramedDeclarationAudit` | A resource's definition, schemas and context block project without throwing. |
| `frame.wire-keys` (ticket 40, not yet shipped) | `FrameWireKeysAudit` | PHP's emitted keys marked for the client have readers in the host vocabulary's `reads` list. |

The declaration audit includes addressable, unlabelled resources and declared action inputs.
It reads projected Data schemas and context blocks without a request; request-bound schema nodes
are not walked. Collection-backed resources get a separate coverage warning; a host schema resolver gets an
inconclusive coverage line. A clean result therefore does not prove a per-request schema or every realm's kit.
See [ADR-0007](adr/0007-widget-names-are-checked-against-a-host-committed-vocabulary.md)
and [ADR-0008](adr/0008-the-registry-port-is-not-an-extension-point.md) for the boundaries.
