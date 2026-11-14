# Frame's API root during expansion

`frame.api_root` defaults to `stud/frame`. The provider registers that surface and the legacy `frame.route_prefix` surface (default `frame`) inside the existing `frame.middleware` enclosure, subject to the existing master/resource switches. Both resolve the same resource handlers, filters, summaries and access gates. `FrameRoutes::mount()` permits an explicit host composition too; identical method/path/domain/action/middleware/defaults reuse the existing route, including a cached collection. A conflicting exposure refuses by name. Route names for the new root use `stud.frame.*`; legacy names remain `frame.*`.

Root ruling 2026-11-13 01:54Z makes Frame a stated exception to the host-called expand pattern: the provider dual-mounts automatically so a host using defaults owes no glue when the JS transport moves. There is no Beam import. Keep both roots until owner-review ticket 16. Empty roots refuse rather than claiming the whole host.

CO-RELEASE: this PHP change must ship in the SAME Frame release as frame-foundation ticket 61, schemastud JS candidate d389b44, whose httpTransport and createFrameKit defaults become `/stud/frame`. Do not release the PHP batch alone. Hosts with `register_route=false` still own their manual realm boundary and adopt the new mount in their own migration.

The first registration owns the name if api_root equals the legacy root. Hosts with a cached config must rebuild it on upgrade so the new api_root default is present. Hosts whose access, CORS, CSRF or forced-JSON policies key on the old URL must cover stud/frame too, or set register_route=false and compose their boundary. The flagship and Tower already choose explicit registration.

Root release and exception authority is durably recorded on owner-review queue row qitem-20261113001849-275aa0d3: frame batch released00:20Z, provider exception ruled01:54Z (coordinator messages, reflected in that row's transitions). The root/charter owns propagation into ticket13e and frame-foundation61; this package author does not edit those shared tracker files.

The duplicate check compares declared middleware metadata without gathering it during registration. A host can append middleware after reuse and dispatch still sees the addition. The same guard correction propagates to the Docs and MCP exports; each owns its release record.


## Automatic route-name safety (13e-fix)

When `frame.register_route` is true, a distinct route sharing an automatic route's exact name
refuses to boot. The check covers the actual names emitted at both roots; it does not reserve
`frame.*` or `stud.frame.*`. A host's `frame.console` is valid. Identical-exposure reuse remains
idempotent and retains the first name. The exception describes both paths, methods, domains,
controllers and declared middleware, rather than choosing a route based on registration order.

The provider validates after `BootProviders` completes, including every provider's application
`booted` callbacks. An automatic registration also refuses a name already held by another route.
Tests or tools that call `Application::boot()` directly without the normal bootstrap events, or
add routes dynamically after bootstrap, must call `FrameRoutes::validateNames()` afterwards.
Validation scans actual route objects, including cached routes, rather than trusting a name
lookup that has already discarded duplicate entries. Route-cache compilation continues refusing
duplicate names. The internal automatic-route marker is persisted in cached action metadata;
it introduces no request field or new route-name namespace.

### Host migration before this fix lands

A host mounting its own `stud.frame.*` or `frame.*` socket must remove the ambiguous automatic
composition. Set `frame.register_route=false`, then explicitly mount the tenant/operator socket
inside its own authentication, tenant and membership boundary. If the host still needs the old
session `/frame` socket, mount that explicitly inside its chosen session boundary too. Rebuild
config and route caches as part of the host's normal deployment. Do not remove the host's guards,
rename a tenant route silently or rely on which provider registers first.

This intentionally changes ambiguous compositions from silent name misdirection to a boot error.
No config values, root URLs, stems or middleware defaults change; both default mounts remain on,
so the matching JS default `/stud/frame` still works. Root approved this refusal at 2026-11-14
18:14Z (owner-review rows bef18b19 / qitem-20261114181414-8485a669). The frame candidate must land
only after the tower-starter companion (automatic registration off and explicit legacy session
mount) is on main and live. The root/host owner confirms that prerequisite; this package does not
change the live host.


## Claude review and the amend round (13e-fix)

Fresh Claude returned PROCEED over `fbd1e857..a31235b9`. The author used one amend; it is not
re-reviewed. The structured review accompanies READY.

| Finding | Disposition and propagation |
|---|---|
| Cached routes were materialized twice per validation | Fixed: getRoutes() is called once, followed by a single pass. Runtime-added routes are still checked against the cached routes; the cached/dynamic regression test remains green. Source change is confined to FrameRoutes. |
| Refusal also stops Artisan and Composer package discovery | Clarified below and in READY. Host configuration and cache migration must precede the new pin. |
| An identical host exposure keeps its name | Clarified: reuse marks that route as an automatic participant, so its actual retained name is checked for duplicates. It does not reserve an absent canonical alias. |
| Fully qualified route type / route-collection names | Replaced with imports in FrameRoutes and PackageRootTest; formatting checked. |

A refusal also prevents Artisan from starting, including route:list, config:clear and Composer's
package:discover. Complete the host's register_route=false change and its config/route cache
rebuild **on the preceding working package**, before upgrading this pin. If an upgrade is already
blocked by cached old configuration, restore the preceding package pin and complete that host
migration before trying the upgrade again. The package does not bypass its guard for console
commands.
