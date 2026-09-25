<?php

namespace Schemastud\Frame\Authorization;

use Illuminate\Database\Eloquent\Model;
use Schemastud\Frame\Contracts\ResourceActionAuthorizer;
use Schemastud\Frame\Registry\ResourceActionDefinition;
use Schemastud\Frame\Registry\ResourceDefinition;

/**
 * Frame's default answer to "may this actor press this action?": no.
 *
 * Frame cannot know the rule behind a URL a producer mounted, so it does not guess. This is the
 * write-axis posture of {@see ResourceAuthorizer} applied to actions: a question nothing can answer
 * affirmatively is refused. A producer that projects actions binds its own
 * {@see ResourceActionAuthorizer}; until it does, frame draws no action buttons at all, which is
 * recoverable, where buttons that 403 are not.
 */
class DenyingResourceActionAuthorizer implements ResourceActionAuthorizer
{
    public function allows(ResourceDefinition $definition, ResourceActionDefinition $action, ?Model $record = null): bool
    {
        return false;
    }
}
