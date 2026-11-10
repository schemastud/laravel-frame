# schemastud/laravel-frame

The **batteries-UX backend rung** for schema-driven admin editors: resolve a resource key to its
whole editor wiring. Family consumers stand on it; the JS side is `@schemastud/frame`.

```bash
composer require schemastud/laravel-frame
```

What it ships:

- **`ResourceDefinition` registry** — resources are declared with Beam's `#[ParticleResource]`;
  Beam is the producer that projects them into Frame's registry.
- **`Contracts\FrameResourceHandler`** — the public CRUD extension point, with `index`, `show`,
  `store`, `update` and `destroy`. In Beam, select a custom implementation through `handler:` on
  the resource declaration.
- **Nine attributes** — `#[Widget]`, `#[WidgetIn]`, `#[Column]`, `#[NotInList]`, `#[RowActions]`,
  `#[Summary]`, `#[Overview]`, `#[ReadOnlyField]` and `#[ResourceRef]` declare widget bindings,
  participation and schema metadata. See the [attribute reference](docs/attributes.md) for their
  targets, contexts, wire output and advisory doctor checks.
- **a served `GET /frame/manifest`** — the one door a frontend reads for the composed editor
  surface;
- sits one level above a filter-schema controller, on `schemastud/laravel-data-schemas` +
  `rushing/laravel-popcorn`.

Start with [`AGENTS.md`](AGENTS.md) (self-ID, conventions), then [`docs/adr/`](docs/adr/) for the
rulings. Tests: `composer test`.

## Registries

This package declares one registry:

- [`frame.resources`](docs/registries/frame.resources.mdx): the producers of resource definitions,
  one registry per name. Frame binds the port; Beam fills it from particle declarations.

It also registers into one registry it doesn't own:

- [`schemas.strategies`](https://github.com/schemastud/laravel-data-schemas/blob/main/docs/registries/schemas.strategies.mdx),
  from `schemastud/laravel-data-schemas`: four strategies that project `#[Widget]`,
  `#[ReadOnlyField]`, widget contexts and resource references into the schema.

Part of the schemastud schema belt; supplied through the fleet's committed git repositories, with
local checkouts overlaid via the gitignored `composer.local.json` (see the workspace runbook).

See [write forms and read schemas](docs/resource-schemas.md) for the declared purpose selector,
configured generator, resolver, compatibility and frontend contract. Beam supplies resource
declarations, read/write handlers and authorization integration.
