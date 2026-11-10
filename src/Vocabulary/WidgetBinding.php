<?php

namespace Schemastud\Frame\Vocabulary;

/**
 * The vocabulary's answer for one name in one context (ticket 05, ruling 2).
 *
 *  - `bound`      one registration is unscoped or scoped to the context. With more than one kit, a name is bound when
 *                 any kit binds it.
 *  - `conflicted` the file lists this name and context under `conflicts`: two kits bind it and different registrations
 *                 answer. It is still bound; no node-type check is made.
 *  - `nodeTypes`  the node types the answering registration's RJSF stock name is valid on (its `stock` list). Null when
 *                 nothing is to be checked: the name is unbound, conflicted here, or answered by a component
 *                 registration (no `stock`). The answering registration is the first scoped one, else the first
 *                 unscoped one (ticket 01's rule).
 */
final class WidgetBinding
{
    /**
     * @param  list<string>|null  $nodeTypes
     */
    public function __construct(
        public readonly bool $bound,
        public readonly bool $conflicted = false,
        public readonly ?array $nodeTypes = null,
    ) {}

    public static function unbound(): self
    {
        return new self(false);
    }
}
