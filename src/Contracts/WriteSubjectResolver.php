<?php

namespace Schemastud\Frame\Contracts;

use Illuminate\Database\Eloquent\Model;
use Schemastud\Frame\Registry\ResourceDefinition;

/**
 * The record a write policy is asked ABOUT, resolved within the resource's own row scope.
 *
 * {@see \Schemastud\Frame\Authorization\ResourceAuthorizer} asks `update`/`delete` about the record
 * the request names. Frame knows the model class and nothing about the rows a principal may address,
 * so its default lookup is `$model::query()->find($id)` — unscoped. That lookup decided EXISTENCE for a
 * scoped resource: a caller naming another owner's row found it, the policy refused it, and the socket
 * answered 403 where the handler's scoped `findOrFail` would have answered 404. Measured 2026-09-24 at
 * `~/Herd/splicewire-app`: an owner's `DELETE …/tokens/records/{another user's token}` returned 403,
 * disclosing that the id exists.
 *
 * The producer that owns the scope binds this port and answers from the same scoped query its handler
 * reads, so an out-of-scope id resolves to nothing here too and the handler keeps owning the 404.
 * Unbound, frame keeps its unscoped lookup.
 */
interface WriteSubjectResolver
{
    /** The record `$id` names within this principal's reach of the resource, or null when it names none. */
    public function resolve(ResourceDefinition $definition, string $id): ?Model;
}
