# ADR-0007 — Widget names are checked against a host-committed vocabulary, by the doctor and never by a throw

**Status:** accepted — frame-foundation ruling 05; documented by ticket 38
**Date:** 2026-11-10
**Repo:** `schemastud/laravel-frame`
**Wayfinding:** `.scratch/splicewire/splicewire-ecosystem/frame-foundation/` tickets 05, 22, 28 and 38.
**Relates to:** ADR-0006 (one form-control keyword).

## Context

PHP declares names, but the host's JavaScript kit owns their registrations. A server-side closed
list would reject valid host widgets, while silently substituting a default hides misspellings.

## Decision

The host commits `resources/frame/vocabulary.json`, generated from its kit's registry with
`pnpm frame:vocabulary --write`. Multiple kits contribute a union with conflicting bindings
recorded explicitly. Laravel-frame reads the file and walks projected declarations; Beam's doctor
reports advisory vocabulary, name, stock node-type and projection findings. Unknown widget names
never throw in PHP. Unresolved names draw the runtime unbound marker in every context.

Structural declaration errors still throw: invalid contexts, wrong subject grain, duplicate form
controls and invalid form modes. The doctor exposes projection failures before a request.

## Consequences

Hosts regenerate the vocabulary when registrations change. Missing or incompatible files make
name checks inconclusive; stale files and request-bound schemas can miss runtime problems.
Conflicting bindings suppress node-type checks rather than inventing a winning kit. The doctor
is advisory, so runtime failures remain possible on hosts that do not run it.
See [the attribute reference](../attributes.md) for IDs and coverage.
