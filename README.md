# schemastud/laravel-frame

The **batteries-UX backend rung** for schema-driven admin editors: resolve a resource key to its
whole editor wiring. Twelve family consumers stand on it; the JS side is `@schemastud/frame`.

```bash
composer require schemastud/laravel-frame
```

What it ships:

- **`ResourceDefinition` registry** — the popcorn-extending resource-definition registry plus the
  5-method CRUD plug (each port has two real adapters in the family);
- **`#[Widget]` → `x-stud-widget` projector** and `#[ReadOnlyField]` — attribute declarations on
  your Data classes become editor widget wiring;
- **a served `GET /frame/manifest`** — the one door a frontend reads for the composed editor
  surface;
- sits one level above a filter-schema controller, on `schemastud/laravel-data-schemas` +
  `rushing/laravel-popcorn`.

Start with [`AGENTS.md`](AGENTS.md) (self-ID, conventions), then [`docs/adr/`](docs/adr/) for the
rulings. Tests: `composer test`.

Part of the schemastud schema belt; supplied through the fleet's committed git repositories, with
local checkouts overlaid via the gitignored `composer.local.json` (see the workspace runbook).
