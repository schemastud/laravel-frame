<?php

namespace Schemastud\Frame\Registry;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One flat route participation descriptor — the router half of the one-spine /
 * three-faces frame runtime (routes · nav · widgets, joined by `routeName`). The
 * manifest emits a `RouteContextEntry[]`; the JS host expands each into a router
 * leaf: it looks the component up by `routeName` in its RouteRegistry, wraps it in
 * the guard registered under `guard` and (when `lazy`) a Suspense boundary, and
 * slots it under the hand-written layout `shell`.
 *
 * HARD GUARDRAIL — flat only. A RouteContextEntry carries per-route properties
 * and NOTHING that encodes parent/child route nesting. Record-nested sub-routes
 * (`circuits/:id/runs`) stay hand-written in the host router; nesting never enters
 * an entry. `mounts` names what renders — one of `list`, `edit`, `detail`,
 * `widget` or `redirect` — but never a child route table.
 *
 * ⚠️ This paragraph used to describe `mounts` as taking `{ widget: name }` or
 * `{ redirect: path }` OBJECT forms, and the constructor's `@param` line pointed the
 * reader at a `$redirect` parameter. **Neither has ever existed.** `mounts` is a flat
 * string; the widget name rides the SEPARATE `$widget` property below; and there is no
 * destination field for a redirect on either side of the wire — the TypeScript
 * interface mirrors these eight parameters and has no `redirect` field either. So an
 * entry can say THAT it redirects and never WHERE, which is why frame's mount
 * dispatcher declines `redirect` as structurally uncarriable rather than as
 * unimplemented. Corrected 2026-08-29, caught by
 * `Splicewire\Beam\Surgeon\DeclarationDocblockAudit` — the estate's only check that
 * reads a declaration against its own docblock, and this was its live finding.
 *
 * The frame package owns this DTO shape; a host supplies the content (the concrete
 * entries + their `routeName` bindings), so the engine stays domain-agnostic.
 */
#[TypeScript]
class RouteContextEntry extends Data
{
    /**
     * @param  string  $routeName  stable identity; the RouteRegistry + nav join key
     * @param  string  $path  the route path (e.g. `circuits`, `threads/assistants/:id`) — flat, never a nested child table
     * @param  string|null  $shell  the hand-written layout shell this leaf slots under (`app`|`settings`|`system`|`knowledge`|null)
     * @param  bool  $lazy  whether the host wraps the component in lazy()/Suspense (keep heavy chunks off the critical path)
     * @param  string|null  $guard  the host guard key wrapping this leaf (`root` → RequireRoot); null = the shell's own auth
     * @param  string  $mounts  what renders: `list`|`edit`|`detail`|`widget`|`redirect` — a flat string, never a serialized object. A `widget` mount names its widget in $widget; a `redirect` mount carries no destination anywhere in this DTO
     * @param  string|null  $widget  when `mounts` is a widget mount, the registered widget name (a heavyweight editor)
     * @param  string|null  $resource  the frame resource key this route lists/edits/details (null for a resource-less standalone page)
     */
    public function __construct(
        public string $routeName,
        public string $path,
        public ?string $shell = null,
        public bool $lazy = false,
        public ?string $guard = null,
        public string $mounts = 'list',
        public ?string $widget = null,
        public ?string $resource = null,
    ) {}
}
