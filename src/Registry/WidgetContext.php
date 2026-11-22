<?php

namespace Schemastud\Frame\Registry;

/**
 * One render context, as an entry of {@see WidgetContextRegistry} (`frame.contexts.<name>`; frame-foundation 86, ruling
 * item 1). A context is a named way of drawing a property, a record or a collection.
 *
 *  - `name`      the context's wire name, one key segment (`Key::SEGMENT_PATTERN`): the `#[WidgetIn]` context and the
 *                block's `known` entry.
 *  - `grain`     the subject it draws ({@see ContextGrain}).
 *  - `fallback`  the contexts whose binding it borrows when it binds none, in order (the block's `inherits` edge). One
 *                step, never transitive; every one must be registered and of the same grain (checked after boot).
 *  - `reserved`  widget names this PROPERTY-grain context admits at the class level (frame's `list-column` reserves
 *                `row-actions`, the `#[RowActions]` sugar). Refused on any other grain.
 *
 * The shell's own guards (`edit`, the `row-cell` heavyweight case) are shell knowledge, not fields here.
 */
final readonly class WidgetContext
{
    /**
     * @param  list<string>  $fallback
     * @param  list<string>  $reserved
     */
    public function __construct(
        public string $name,
        public ContextGrain $grain,
        public array $fallback = [],
        public array $reserved = [],
    ) {}
}
