<?php

namespace Schemastud\Frame\Http\Controllers;

use Schemastud\Frame\Registry\AdminResourceRegistry;

/**
 * GET /frame/manifest -> AdminResourceDefinition[] (the served manifest). Resolves
 * the whole editor wiring for every registered resource; the frontend type IS this
 * projection (generate-once parity). Middleware/gating is the host's — the route
 * applies config('frame.middleware') so a host can put the surface behind its own
 * staff gate (numero: can:bypass-marquee).
 */
class FrameManifestController
{
    public function __invoke(AdminResourceRegistry $registry): array
    {
        return [
            'resources' => $registry->all(),
        ];
    }
}
