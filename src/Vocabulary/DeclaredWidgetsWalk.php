<?php

namespace Schemastud\Frame\Vocabulary;

/** What {@see DeclaredWidgets::walk()} read from one resource: every declared name, and every projection that threw. */
final class DeclaredWidgetsWalk
{
    /**
     * @param  list<DeclaredWidget>  $widgets  in walk order: write schema, read schema, each action input, context block
     * @param  list<ProjectionFailure>  $failures
     */
    public function __construct(
        public readonly string $resource,
        public readonly array $widgets,
        public readonly array $failures,
    ) {}
}
