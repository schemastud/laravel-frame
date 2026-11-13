# Provider dual mounts Frame during the API root expansion

Decision: 2026-11-13, root rulings 00:19Z and 01:54Z on owner-review-20261112 ticket 13e; durable queue row qitem-20261113001849-275aa0d3.

Frame's provider serves both the new `frame.api_root` (default `stud/frame`) and retained `frame.route_prefix` (default `frame`) under the existing middleware and switches until ticket 16. This is a stated exception to the other packages' host-called expansion pattern: frame-foundation ticket 61 moves the JS transport and kit default to `/stud/frame` in the SAME release. A host using defaults therefore owes no composition glue. The PHP candidate must not release separately from that JS change (candidate d389b44).

Frame imports nothing from Beam. An identical route exposure reuses the first registration, including when routes came from a cache; a conflicting exposure refuses by name. A host using URL-based policy fences must cover both roots or disable provider registration and compose its boundary. The flagship and Tower already disable the provider surface and own their realm mounts. Rebuild cached config when upgrading. Ticket 16 removes the legacy exposure on the owner's word.
