<?php

namespace Schemastud\Frame\Attributes;

use Attribute;

/**
 * Sugar for `#[WidgetIn('list-column', …)]` — the common "show this property as a
 * list column" case spelled shorter. `$widget` null keeps the widget-default;
 * `$filterable` folds into the authoring options bag as `['filterable' => true]`.
 *
 *   #[Column]
 *   #[Column('badge', label: 'Status', sort: 2, filterable: true)]
 *
 * ## `$widget` is the PRESENTATION KIND, and `$options` configures it
 *
 * `$widget` has always projected through to `byNode[field]['list-column'].widget`, and
 * nothing on the client read it — so a declaration could name how a cell renders and the
 * host still hand-wrote the closure. The client half (`resolveColumns` → the frame column
 * kinds) now consumes that name, which makes `#[Column('badge')]` the declaration of a
 * badge cell rather than a note about one.
 *
 * `$options` is the per-kind configuration bag, emitted VERBATIM (the server never merges
 * it — see {@see WidgetContextProjector::entry()}). It is a separate parameter from
 * `$filterable` rather than a replacement, because `filterable` is a FILTER fact and the
 * kind options are a RENDER fact; they share the bag on the wire and they are not the same
 * declaration. When both are given, `filterable` is folded in alongside.
 *
 *   #[Column('text',   options: ['mono' => true, 'placeholder' => '—'])]
 *   #[Column('badge',  options: ['variant' => 'secondary'])]
 *   #[Column('number', options: ['zeroAsDash' => true])]
 *   #[Column('date')]
 *
 * A kind name frame does not know is NOT an error here: an unknown kind falls through to
 * the default cell (and the host's own `cell` override, which still wins by field). Frame's
 * vocabulary is a client-side fact, so validating it server-side would be exactly the
 * "a check whose answer depends on the host must not throw" mistake.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Column extends WidgetIn
{
    /**
     * @param  string|null  $widget  the presentation kind (`text`|`badge`|`badges`|`number`|`date`, or any name the host's frame build knows); null keeps the widget-default
     * @param  string|null  $label  the column header
     * @param  int|null  $sort  column order within the list
     * @param  bool  $filterable  fold `['filterable' => true]` into the options bag
     * @param  array<string, mixed>|null  $options  the per-kind render options, emitted verbatim to the wire
     */
    public function __construct(
        ?string $widget = null,
        ?string $label = null,
        ?int $sort = null,
        bool $filterable = false,
        ?array $options = null,
    ) {
        parent::__construct(
            'list-column',
            $widget ?? true,
            self::bag($filterable, $options),
            $sort,
            $label,
        );
    }

    /**
     * Merge the two authoring inputs into the single options bag the wire carries.
     * Neither alone ⇒ null, which keeps `options` off the entry entirely (the strict
     * no-op the projector already relies on).
     *
     * @param  array<string, mixed>|null  $options
     * @return array<string, mixed>|null
     */
    private static function bag(bool $filterable, ?array $options): ?array
    {
        if (! $filterable) {
            return $options;
        }

        // An explicit `options` key wins over the sugar, so the two never contradict silently.
        return array_merge(['filterable' => true], $options ?? []);
    }
}
