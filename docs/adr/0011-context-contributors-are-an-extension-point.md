# ADR-0011 — Context contributors are an extension point

**Status:** accepted — frame-foundation decision 87 (auto-grill, 2026-11-20); built by ticket 93
**Date:** 2026-11-22
**Repo:** `schemastud/laravel-frame`
**Wayfinding:** `.scratch/splicewire/splicewire-ecosystem/frame-foundation/` tickets 87 (the ruling) and 93.
**Relates to:** ADR-0008 (the resource registry port is not an extension point; it stands unamended), ADR-0010
(render contexts are registry entries).

## Context

`ContextManifest` took exactly one nullable `ResourceContextContributor`. Three contributors stood behind it in three
packages, composed by nesting: laravel-beam bound the port to `CollectionResourceContextNodes` wrapping
`ContributionContextNodes`, and laravel-beam-exec wrapped that with `BoardCardContextNodes` through `extend`. Their
order was whatever the nesting happened to be, and a host could neither see it nor replace one of them.

ADR-0008 says the registry port is not an extension point. That ADR is about the **resource** registry while beam is
its one producer. The contributor set has three producers, which is the difference.

## Decision

1. **Registry.** `Schemastud\Frame\Registry\ContextContributorRegistry`, `#[IsRegistry(root:
   'frame.context-contributors', entryType: Closure::class, onKeyDuplicate: Supersede, populationRequirement:
   Optional)]`, implementing `Laddered`, `RecordsRegistrants` and `RecordsSupersession`. Frame's provider binds it as
   a singleton.
2. **Entries are factories.** An entry is `Closure(): ResourceContextContributor`, called on every read and never
   cached by frame. Each package keeps its contributor as its own `scoped()` binding and its factory resolves that
   binding through the current container when called; a factory never captures an instance taken at boot (Octane).
3. **Order.** Rungs `declared` › `contributed` (declarative; the kernel never climbs). Contributors fold in position
   order, a later position wins a shared context whole, a `Supersede` replacement keeps its slot, and the resource's
   own reflected declaration wins over every contributor. The fold reproduces the old nesting byte for byte.
4. **The port goes; the interface stays.** `ResourceContextContributor::nodesFor()` is what a factory returns. Nothing
   binds the port abstract, so registration is the one way to contribute. `ContextManifest`'s first constructor
   argument becomes `?ContextContributorRegistry $contributors = null`; `new ContextManifest` still reads reflection
   only. `CollectionResourceContextNodes` and `BoardCardContextNodes` no longer wrap anything.
5. **Keys and phase.** A package registers under its own key from its provider's `boot()`: `beam.contributions`,
   `beam.collections` (laravel-beam, in that order), `beam-exec.board-cards` (laravel-beam-exec). The registrant is
   the package's composer name.
6. **Visibility.** `popcorn:keys frame.context-contributors` lists the contributors in answer order with their
   registrants, and `--supersessions` lists what a host replaced. No key reaches the wire.

## Consequences

- The contributor set **is** an extension point; the resource registry port (ADR-0008) still is not.
- Position follows provider boot order, which nobody chooses: a later-sorting package beats beam's contributors on a
  shared (pointer, context), and a hand-listed provider reorders them. Both are visible in `popcorn:keys`; a host
  corrects either by superseding. No such collision exists today.
- A host that bound or extended the port must register instead (CONFORM).
- The Octane sandbox is not proven: a test proves a factory runs after a scope reset, which is not Octane's clone.
