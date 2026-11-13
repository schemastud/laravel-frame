# Frame's API root during expansion

`frame.api_root` defaults to `stud/frame`. The provider registers that surface and the legacy `frame.route_prefix` surface (default `frame`) inside the existing `frame.middleware` enclosure, subject to the existing master/resource switches. Both resolve the same resource handlers, filters, summaries and access gates. `FrameRoutes::mount()` permits an explicit host composition too; identical method/path/domain/action/middleware/defaults reuse the existing route, including a cached collection. A conflicting exposure refuses by name. Route names for the new root use `stud.frame.*`; legacy names remain `frame.*`.

Root ruling 2026-11-13 01:54Z makes Frame a stated exception to the host-called expand pattern: the provider dual-mounts automatically so a host using defaults owes no glue when the JS transport moves. There is no Beam import. Keep both roots until owner-review ticket 16. Empty roots refuse rather than claiming the whole host.

CO-RELEASE: this PHP change must ship in the SAME Frame release as frame-foundation ticket 61, schemastud JS candidate d389b44, whose httpTransport and createFrameKit defaults become `/stud/frame`. Do not release the PHP batch alone. Hosts with `register_route=false` still own their manual realm boundary and adopt the new mount in their own migration.

The first registration owns the name if api_root equals the legacy root. Hosts with a cached config must rebuild it on upgrade so the new api_root default is present. Hosts whose access, CORS, CSRF or forced-JSON policies key on the old URL must cover stud/frame too, or set register_route=false and compose their boundary. The flagship and Tower already choose explicit registration.

Root release and exception authority is durably recorded on owner-review queue row qitem-20261113001849-275aa0d3: frame batch released00:20Z, provider exception ruled01:54Z (coordinator messages, reflected in that row's transitions). The root/charter owns propagation into ticket13e and frame-foundation61; this package author does not edit those shared tracker files.
