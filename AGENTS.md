> You are in **schemastud/laravel-frame** — the batteries-UX backend rung for schema-driven admin editors.

Ships the resource-definition registry (`ResourceDefinition`), the `#[Widget]` -> `x-stud-widget`
projector, `#[ReadOnlyField]`, and a served `GET /frame/manifest`. Sits one level above a
filter-schema controller; resolves a resource key to its whole editor wiring. Pairs with
`@schemastud/frame` on the JS side.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
