<?php

namespace Schemastud\Frame\Contracts;

use Schemastud\Frame\Registry\ResourceDefinition;

/**
 * The REACH axis of frame's resource socket — *may this actor address this resource at all?* —
 * asked once per request, before any verb-specific question and before any handler runs.
 *
 * ## Why this is a port and not a rule
 *
 * {@see \Schemastud\Frame\Authorization\ResourceAuthorizer} answers the WRITE axis with Laravel's own
 * policy vocabulary, which frame can ask because a policy is about a MODEL and frame is handed the
 * model. The reach question is about something frame deliberately does not own: which *realm* a
 * resource belongs to. Frame carries {@see \Schemastud\Frame\Realm\RealmDefinition} as a SHAPE for a
 * producer to populate ("the foundation owns the shape; a host supplies the concrete instances") and
 * has no registry of realms, no membership map and no notion of an entitlement. A rule written here
 * would have to invent all three.
 *
 * The producer above frame does own them — `Splicewire\Beam\Particle\ParticleResourceRegistry` is
 * "the declared membership authority" and `Splicewire\Beam\Realm\RealmRegistry` holds the realms — so
 * beam binds {@see \Splicewire\Beam\Realm\RealmEntitlementResourceGate} and frame ships
 * {@see \Schemastud\Frame\Authorization\OpenResourceAccessGate}, which permits everything. A
 * frame-only host is therefore byte-for-byte what it was.
 *
 * ## Why it is asked in the socket rather than in middleware or a handler
 *
 * The same two candidates {@see \Schemastud\Frame\Authorization\ResourceAuthorizer}'s docblock
 * rejects for the write axis lose here for the same reasons, and the READ leak this exists to close
 * is the evidence:
 *
 *  - **`frame.middleware`** is ONE ambient list for the whole route group. Every starter sets it to
 *    `['web','auth']`. One socket serves every realm, so no value of that key can refuse the operator
 *    realm's resources without refusing the tenant realm's too — which is exactly why the leak
 *    survived a host that had already thought about this key and written four screens of reasoning
 *    onto it.
 *  - **The {@see FrameResourceHandler} port** has six implementations across the estate and cannot
 *    compel a check; a gate that must be remembered N times is not a gate.
 *
 * ## Deny means 403, not 404
 *
 * The resource EXISTS and is registered; what is refused is this actor. Answering 404 would be the
 * other defensible posture (it hides the resource's existence) but it is not this port's call to
 * make: frame already spends 404 on "unknown frame resource", and spending it on "known, refused"
 * would make an unregistered key and a gated one indistinguishable in every log and every client.
 */
interface ResourceAccessGate
{
    /**
     * May the CURRENT actor address `$definition` on the frame socket at all?
     *
     * Asked with no verb and no record id on purpose: this is the axis before both. An
     * implementation that needs the verb is answering the write question, which
     * {@see \Schemastud\Frame\Authorization\ResourceAuthorizer} already owns.
     */
    public function allowsResource(ResourceDefinition $definition): bool;
}
