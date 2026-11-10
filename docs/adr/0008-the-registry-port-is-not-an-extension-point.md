# ADR-0008 — The registry port is not an extension point while one producer implements it

**Status:** accepted — frame-foundation ruling 05; documented by ticket 38
**Date:** 2026-11-10
**Repo:** `schemastud/laravel-frame`
**Wayfinding:** `.scratch/splicewire/splicewire-ecosystem/frame-foundation/` tickets 05 and 38.
**Relates to:** ADR-0001 (Frame owns presentation contracts and its handler plug).

## Context

The composite resource registry can hold several producers. Its documentation taught hosts to
attach a second registry or hand-build `ResourceDefinition` values, even though Beam is the one
producer of resource declarations in the family. That duplicates declaration authority.

## Decision

Keep the registry port and composite machinery, but do not advertise attaching another producer
as a supported extension point while Beam is its one producer. Declare resources with
`#[ParticleResource]`; Beam projects them into Frame's registry. No class moves and no runtime
registration behavior changes.

The public CRUD extension point is `Contracts\FrameResourceHandler`: `index`, `show`, `store`,
`update` and `destroy`. Beam selects a custom handler through the resource's `handler:` slot.

## Consequences

The registry docs retain their generated facts and explain the existing composite behavior,
without teaching a second producer. A genuine second producer would require revisiting this
boundary. The in-memory store remains an implementation of the port, not another declaration
path hosts are encouraged to populate.
