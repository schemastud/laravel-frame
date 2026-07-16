<?php

namespace Schemastud\Frame\Attributes;

use Attribute;
use Schemastud\Frame\Registry\ContextManifest;
use Schemastud\Frame\Strategies\WidgetContextsStrategy;

/**
 * Declares that a property (or, class-level, a whole record) participates in a
 * named render CONTEXT — the WidgetContextRegistry server-side projection. One
 * property can carry several (`IS_REPEATABLE`): a different widget in `edit`, in
 * `list-column`, in `row-cell`. Projected to the `x-stud-widget-contexts` map by
 * {@see WidgetContextsStrategy}; the class-level
 * declarations are gathered by {@see ContextManifest}.
 *
 * The five contexts are a closed enum: `edit`, `detail`, `list-column`,
 * `list-item`, `row-cell`. `list-item` is whole-record only (class-level); the
 * other four are per-property. The sugar attributes {@see Column}, {@see NotInList}
 * and {@see RowActions} are the common cases spelled shorter.
 *
 *   #[WidgetIn('edit', 'email-input', ['autocomplete' => 'email'])]
 *   #[WidgetIn('row-cell', inheritsBinding: true)]
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class WidgetIn
{
    /**
     * @param  string  $context  one of edit|detail|list-column|list-item|row-cell
     * @param  string|bool  $bind  false = don't participate; true = participate + widget-default; 'name' = bind that widget name
     * @param  array<string, mixed>|null  $options  authoring bag, emitted verbatim to the wire (server does NOT merge)
     * @param  int|null  $sort  optional ordering hint
     * @param  string|null  $label  optional per-context label override
     * @param  bool|string  $inheritsBinding  false|true|'string' — gates whether name AND options inherit
     * @param  bool  $heavyweight  a heavyweight edit widget doesn't inline into a cell
     */
    public function __construct(
        public string $context,
        public string|bool $bind = true,
        public ?array $options = null,
        public ?int $sort = null,
        public ?string $label = null,
        public bool|string $inheritsBinding = true,
        public bool $heavyweight = false,
    ) {}
}
