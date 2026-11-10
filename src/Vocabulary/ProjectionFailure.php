<?php

namespace Schemastud\Frame\Vocabulary;

/**
 * One projection {@see DeclaredWidgets} could not make: generating a schema or building the context block threw
 * (an unknown context, a context at the wrong grain, `#[Widget]` beside an ordinary edit entry, a generator that
 * accepts no class, …). The walk records it and goes on to the resource's other sources; it never rethrows
 * (ticket 05, ruling 1: "a projection that throws is yielded as a failure"; the doctor reports it as
 * `frame.projection`).
 */
final class ProjectionFailure
{
    /**
     * @param  DeclaredWidget::Source*  $source
     * @param  class-string  $class  the Data class that was being projected
     * @param  class-string<\Throwable>  $exception
     */
    public function __construct(
        public readonly string $resource,
        public readonly string $source,
        public readonly string $class,
        public readonly string $exception,
        public readonly string $message,
        public readonly ?string $action = null,
    ) {}
}
