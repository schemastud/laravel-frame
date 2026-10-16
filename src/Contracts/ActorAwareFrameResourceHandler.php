<?php

namespace Schemastud\Frame\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/** An opt-in transport boundary: explicit null means guest, never an absent ambient request. */
interface ActorAwareFrameResourceHandler
{
    /** Return a bound clone; binding one request must not change a shared handler for the next. */
    public function forActor(?Authenticatable $actor, ?string $realm = null): FrameResourceHandler;
}
