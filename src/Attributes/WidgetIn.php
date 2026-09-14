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
 * The seven contexts are a closed enum spanning THREE SUBJECT GRAINS, and the grain
 * decides where a context may be declared:
 *
 *  - property   `edit`, `detail`, `list-column`, `row-cell` — one field of one record;
 *               declared on a property.
 *  - record     `list-item` — one whole record (a card body, a row); class-level only.
 *  - collection `summary`, `overview` — the whole collection of records, compressed to
 *               figures (`summary`) or expanded to a card with a body (`overview`);
 *               class-level only. `overview` inherits `summary`'s binding when unbound.
 *
 * "A summary of THIS record" is NOT a new context: that is `list-item`. The two
 * collection contexts describe the resource as a set — a stat tile, a dashboard card —
 * never any single record in it. Declaring a record or collection context on a
 * property, or a property context on the class, throws at projection time.
 *
 * The sugar attributes {@see Column}, {@see NotInList}, {@see RowActions},
 * {@see Summary} and {@see Overview} are the common cases spelled shorter.
 *
 *   #[WidgetIn('edit', 'email-input', ['autocomplete' => 'email'])]
 *   #[WidgetIn('row-cell', inheritsBinding: true)]
 *   #[WidgetIn('summary', 'stat-row')]                       // class-level
 *   #[WidgetIn('overview', 'figure-card', ['period' => 'month'])] // class-level
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class WidgetIn
{
    /**
     * @param  string  $context  one of edit|detail|list-column|row-cell (property), list-item (record), summary|overview (collection)
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
