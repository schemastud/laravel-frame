<?php

use Schemastud\Frame\Routing\FrameRoutes;

// Foundation exception: the matching JS default ships in the same release, so default hosts owe no glue.
// Keep the prior exposure until owner-review ticket 16 contracts it.
FrameRoutes::legacy();
FrameRoutes::mount();
