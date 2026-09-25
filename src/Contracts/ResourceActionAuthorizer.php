<?php

namespace Schemastud\Frame\Contracts;

use Illuminate\Database\Eloquent\Model;
use Schemastud\Frame\Registry\ResourceActionDefinition;
use Schemastud\Frame\Registry\ResourceDefinition;

/**
 * May the CURRENT actor press this action? The port a producer answers through (ADR-0005).
 *
 * Frame owns the question and cannot answer it: an action is the producer's request to a URL the
 * producer mounted and gates, and frame knows neither the rule behind that mount nor the ability it
 * checks. So frame asks here, and the producer that projected the action answers with the SAME rule
 * its mount enforces — which is what keeps a drawn button and the refusal behind it from disagreeing.
 *
 * The answer is ADVISORY. It decides whether frame draws the button (it rides the per-actor
 * `can.actions` map on the {@see \Schemastud\Frame\Registry\ContextManifest}); the URL's own mount is
 * the enforcement and refuses a request this answered wrongly about.
 *
 * Frame's default ({@see \Schemastud\Frame\Authorization\DenyingResourceActionAuthorizer}) answers
 * false: a host that projects actions and binds no answer gets no buttons rather than buttons that 403.
 */
interface ResourceActionAuthorizer
{
    /**
     * @param  Model|null  $record  the record a `record`-scope action would act on, or null for the class-level
     *                              (manifest) probe. A `resource`-scope action is always asked with null.
     */
    public function allows(ResourceDefinition $definition, ResourceActionDefinition $action, ?Model $record = null): bool;
}
