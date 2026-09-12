<?php

namespace Schemastud\Frame\Authorization;

use Schemastud\Frame\Contracts\ResourceAccessGate;
use Schemastud\Frame\Registry\ResourceDefinition;

/**
 * Frame's shipped {@see ResourceAccessGate}: every registered resource is reachable.
 *
 * This is NOT the write axis's fail-closed default, and the asymmetry is the same one
 * {@see ResourceAuthorizer} argues. A missing POLICY is an omission about a model frame can see, so
 * refusing is the honest reading. A missing realm GATE is not an omission at all at a frame-only
 * host: there are no realms there, so there is no membership fact to have forgotten. Denying by
 * default would close every socket in the estate on a question nobody asked, which is a different
 * defect, not a stricter version of this one.
 *
 * The refusing answer belongs to the producer that owns realms. Beam binds
 * `Splicewire\Beam\Realm\RealmEntitlementResourceGate` over this.
 */
class OpenResourceAccessGate implements ResourceAccessGate
{
    public function allowsResource(ResourceDefinition $definition): bool
    {
        return true;
    }
}
