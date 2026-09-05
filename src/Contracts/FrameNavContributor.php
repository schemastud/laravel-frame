<?php

namespace Schemastud\Frame\Contracts;

use Schemastud\Frame\Registry\NavManifest;
use Schemastud\Frame\Registry\RouteContextEntry;

/**
 * An OPTIONAL plug port: something outside frame may add the ROUTER + NAVIGATION half of
 * `/frame/manifest` — the `nav` tree and the flat {@see RouteContextEntry}[] a JS host expands
 * into router leaves.
 *
 * ## Why this port exists at all
 *
 * Frame already publishes {@see RouteContextEntry} and says of it: *"The frame package owns this
 * DTO shape; a host supplies the content … so the engine stays domain-agnostic."* It published the
 * shape and never published a way to GET one. The measurable consequence: exactly one host in the
 * estate emitted `nav` and `routeContext`, and it did so by hand-writing a private copy of
 * {@see \Schemastud\Frame\Http\Controllers\FrameManifestController}. Every host cut from the starter
 * inherited a manifest with no navigation in it and no seam to put one in.
 *
 * ## What is licensed, and what is not
 *
 * `api-surface-coherence` **142** rules realm membership a **host-side list**, and **141** rules the
 * mounts stay spelled out. So this port does NOT let a package declare its own nav seat or realm —
 * the host still writes the list. What moves across the port is the **projection over that list**:
 * ordering, grouping, gating, active-state stamping, and the resource→leaf derivation that every
 * host would otherwise re-implement. The list is host code; the projection is package code.
 *
 * ## It satisfies ADR-0001 rather than sidestepping it
 *
 * *A field on frame's contract must be read by frame, or by frame's own plug seam.* This adds no
 * field to {@see \Schemastud\Frame\Registry\ResourceDefinition}. It is a METHOD frame calls and
 * whose result frame merges into the payload it already emits — the same shape as
 * {@see ResourceContextContributor}, which the same ADR's reasoning already licensed.
 *
 * ## Unbound is the normal case, and declining is the second normal case
 *
 * A pure-frame host binds nothing: the port arrives as a nullable constructor argument on
 * {@see NavManifest}, never by service location, so the payload is byte-identical to before and
 * `new NavManifest` still works with no container. A bound contributor may additionally return
 * `null` for a realm it has no navigation for — *"is there a navigation registered here?"* is a
 * fact about the HOST, and AGENTS.md §*A check whose answer depends on the host must not throw*
 * makes that an absence, never a fatal.
 */
interface FrameNavContributor
{
    /**
     * The navigation + router block for one realm, or `null` when this host declares no
     * navigation for it.
     *
     * `$realm` is read off the matched route's `realm` default, so a host that mounts the manifest
     * once per realm (`/api/v1/frame/manifest`, `/api/operator/frame/manifest`) gets a
     * realm-scoped answer from the same controller, and a host that mounts it once gets `null`.
     *
     * @return array{nav: array<string, mixed>, routeContext: array<int, RouteContextEntry>}|null
     */
    public function contributeNav(?string $realm): ?array;
}
