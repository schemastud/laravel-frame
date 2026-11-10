<?php

namespace Schemastud\Frame\Vocabulary;

/**
 * One widget name a resource declares, as {@see DeclaredWidgets} reads it from what frame would serve
 * (ticket 05, ruling 1). The audit checks `name` against the vocabulary in `context`.
 *
 *  - `source`  where it was read: the served write schema, the read schema, one action's input schema (`action` names
 *              it), or the resource's context block (`byNode`).
 *  - `pointer` for a schema source, the RFC 6901 JSON Pointer of the node inside that schema (`/properties/body`,
 *              `""` for the root); for the context block, the `byNode` key (`""` the root, a property name, or a
 *              contributed dotted pointer).
 *  - `context` the context the name is checked in: a schema's `x-widget` is `edit` on the write schema and an action
 *              input, and `detail` on the read schema (primitive nodes only); a context entry's `widget` is its entry's
 *              context.
 *  - `nodeType` the schema node's JSON type (`string`, `integer`, …; one non-null member of a type list), or null when
 *              the node declares none, and always null for a context-block entry.
 *  - `heavyweight` a context entry marked heavyweight (a full-surface editor, checked against names bound for `edit`).
 */
final class DeclaredWidget
{
    public const SourceWriteSchema = 'write-schema';

    public const SourceReadSchema = 'read-schema';

    public const SourceActionInput = 'action-input';

    public const SourceContextBlock = 'context-block';

    /**
     * @param  self::Source*  $source
     * @param  class-string  $class  the Data class the source was projected from
     */
    public function __construct(
        public readonly string $resource,
        public readonly string $source,
        public readonly string $class,
        public readonly string $pointer,
        public readonly string $context,
        public readonly string $name,
        public readonly ?string $nodeType = null,
        public readonly bool $heavyweight = false,
        public readonly ?string $action = null,
    ) {}
}
