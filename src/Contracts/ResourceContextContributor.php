<?php

namespace Schemastud\Frame\Contracts;

use Schemastud\Frame\Registry\ContextManifest;

/**
 * An OPTIONAL plug port: something outside frame may add render-context participation to a
 * resource's context block, keyed by the resource's registry KEY.
 *
 * ## What frame learns, and what it deliberately does not
 *
 * Frame learns *that* extra `byNode` entries exist for a key. It learns nothing about WHY —
 * no contribution, no contributor, no owning package, no vendor above it. That asymmetry is
 * the whole point: the surface is the same one frame already publishes as
 * {@see FrameResourceHandler}, whose docblock invites exactly this, and it is what keeps
 * frame a presentation-tier package with no knowledge of the tier above (ADR-0092,
 * *"Frame stays in Schemastud"*).
 *
 * The alternative shapes were assessed and rejected (particle-contribution-seam ticket 17 §A1):
 * {@see ContextManifest} reading a consumer's registry directly would put a foreign class
 * inside schemastud and reopen the narrowing ticket 10 just closed; having the CALLER compute
 * the extra nodes would land a consumer-shaped line in a HOST controller, which is the
 * host-names-it defect in its mildest form.
 *
 * ## It satisfies ADR-0001 rather than sidestepping it
 *
 * *A field on frame's contract must be read by frame.* This adds no field to
 * {@see \Schemastud\Frame\Registry\ResourceDefinition} at all — it is a METHOD frame calls and
 * whose result frame consumes, merging it into the block it already builds. Nothing here is
 * carried on frame's behalf for someone else to read, which is the shape ADR-0001 forbids.
 *
 * ## Unbound is the normal case
 *
 * A pure-frame host binds nothing and gets an unchanged manifest by construction: the port
 * arrives as a nullable constructor argument on {@see ContextManifest}, never by service
 * location, so the class stays constructible with `new` and testable without a container.
 */
interface ResourceContextContributor
{
    /**
     * Extra `byNode` entries for one resource key, in the same shape
     * {@see ContextManifest::forResource()} builds: pointer => `{context => entry}`.
     *
     * The pointer for a contributed node is DOTTED — `commerce.plan` — because the value it
     * describes is nested under its sub-projection key in the projected row. `byNode` is a
     * plain string map, so a dotted pointer is a legal key with no schema change; the JS side
     * resolves the path when it reads the value.
     *
     * Returns `[]` for a key nothing contributes to, which is the overwhelmingly common case.
     *
     * @return array<string, array<string, mixed>>
     */
    public function nodesFor(string $key): array;
}
