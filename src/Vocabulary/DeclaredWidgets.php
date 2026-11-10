<?php

namespace Schemastud\Frame\Vocabulary;

use Schemastud\Frame\Keywords;
use Schemastud\Frame\Registry\ContextManifest;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Registry\ResourceSchemas;
use Throwable;

/**
 * Walks one {@see ResourceDefinition} and lists every widget name it declares (ticket 05, ruling 1), reading what frame
 * would serve rather than reflecting frame's attributes: the write schema and the read schema (through the schema
 * endpoint's own {@see ResourceSchemas}), each declared action's input schema, and the context block
 * ({@see ContextManifest}, with the host's contributor when one is bound). A projection that throws is a
 * {@see ProjectionFailure} in the result; the walk never rethrows.
 *
 * Which context a name is checked in (ruling 1): a node's `x-widget` on the write schema or an action input is
 * `edit`; on the read schema it is `detail`, for a primitive node only (on an object or array node the keyword names
 * the form's field and mounts nowhere else); a context entry's `widget` is its entry's context. `row-actions` is
 * yielded where it is declared (the class-level `list-column` entry, pointer `""`); whether it may stand there is the
 * audit's call.
 *
 * A schema is walked through `properties`, `items`, `prefixItems`, `additionalProperties`, `allOf`/`anyOf`/`oneOf`
 * and `$defs`/`definitions`: a nested Data class is reached as far as the generated schema inlines or defines it. A
 * `$ref` is not followed. A {@see \Schemastud\Frame\Contracts\ResourceSchemaResolver}'s per-request schema is not
 * walked.
 *
 * Frame audits read declarations through this class, never by reflecting frame's attributes (CONFORM 05-13).
 */
class DeclaredWidgets
{
    public function __construct(
        protected ResourceSchemas $schemas,
        protected ContextManifest $manifest,
    ) {}

    public function walk(ResourceDefinition $definition): DeclaredWidgetsWalk
    {
        $widgets = [];
        $failures = [];

        foreach ([ResourceSchemas::PurposeWrite => DeclaredWidget::SourceWriteSchema, ResourceSchemas::PurposeRead => DeclaredWidget::SourceReadSchema] as $purpose => $source) {
            $class = $this->schemas->dataClass($definition, $purpose);

            try {
                $schema = $this->schemas->forResource($definition, $purpose);
            } catch (Throwable $e) {
                $failures[] = new ProjectionFailure($definition->key, $source, $class, $e::class, $e->getMessage());

                continue;
            }

            $this->visit($schema, '', $definition->key, $source, $class, null, $widgets);
        }

        foreach ($definition->actions as $action) {
            if ($action->input === null) {
                continue;
            }

            try {
                $schema = $this->schemas->forActionInput($action);
            } catch (Throwable $e) {
                $failures[] = new ProjectionFailure($definition->key, DeclaredWidget::SourceActionInput, $action->input, $e::class, $e->getMessage(), $action->key);

                continue;
            }

            $this->visit($schema, '', $definition->key, DeclaredWidget::SourceActionInput, $action->input, $action->key, $widgets);
        }

        try {
            $block = $this->manifest->forResource($definition->data, $definition->layout, $definition->key);
        } catch (Throwable $e) {
            $failures[] = new ProjectionFailure($definition->key, DeclaredWidget::SourceContextBlock, $definition->data, $e::class, $e->getMessage());

            return new DeclaredWidgetsWalk($definition->key, $widgets, $failures);
        }

        foreach ($block['byNode'] as $pointer => $contexts) {
            foreach ($contexts as $context => $entry) {
                if (is_array($entry) && is_string($entry['widget'] ?? null)) {
                    $widgets[] = new DeclaredWidget(
                        resource: $definition->key,
                        source: DeclaredWidget::SourceContextBlock,
                        class: $definition->data,
                        pointer: (string) $pointer,
                        context: (string) $context,
                        name: $entry['widget'],
                        heavyweight: ($entry['heavyweight'] ?? false) === true,
                    );
                }
            }
        }

        return new DeclaredWidgetsWalk($definition->key, $widgets, $failures);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<DeclaredWidget>  $widgets
     */
    private function visit(array $node, string $pointer, string $resource, string $source, string $class, ?string $action, array &$widgets): void
    {
        $name = $node[Keywords::Widget] ?? null;

        if (is_string($name)) {
            $type = $this->nodeType($node);
            $read = $source === DeclaredWidget::SourceReadSchema;

            if (! $read || ! in_array($type, ['object', 'array'], true)) {
                $widgets[] = new DeclaredWidget($resource, $source, $class, $pointer, $read ? 'detail' : 'edit', $name, $type, action: $action);
            }
        }

        foreach (['properties', '$defs', 'definitions', 'patternProperties'] as $map) {
            foreach (is_array($node[$map] ?? null) ? $node[$map] : [] as $key => $child) {
                if (is_array($child)) {
                    $this->visit($child, $pointer.'/'.$map.'/'.self::escape((string) $key), $resource, $source, $class, $action, $widgets);
                }
            }
        }

        foreach (['items', 'additionalProperties'] as $single) {
            if (is_array($node[$single] ?? null) && ! array_is_list($node[$single])) {
                $this->visit($node[$single], $pointer.'/'.$single, $resource, $source, $class, $action, $widgets);
            }
        }

        foreach (['prefixItems', 'allOf', 'anyOf', 'oneOf'] as $list) {
            foreach (is_array($node[$list] ?? null) ? $node[$list] : [] as $i => $child) {
                if (is_array($child)) {
                    $this->visit($child, $pointer.'/'.$list.'/'.$i, $resource, $source, $class, $action, $widgets);
                }
            }
        }
    }

    /** The node's JSON type: a string `type`, else the one non-null member of a type list; null otherwise. */
    private function nodeType(array $node): ?string
    {
        $type = $node['type'] ?? null;

        if (is_string($type)) {
            return $type;
        }

        if (is_array($type)) {
            $types = array_values(array_filter($type, fn ($t) => is_string($t) && $t !== 'null'));

            return count($types) === 1 ? $types[0] : null;
        }

        return null;
    }

    private static function escape(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
