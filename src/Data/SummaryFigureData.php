<?php

namespace Schemastud\Frame\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One figure on a resource's summary: a keyed, labelled value with an optional semantic tone.
 *
 * ## The tone vocabulary is CLOSED, and it is the client's
 *
 * `$tone` is not free text. The reference client renders it through `@schemastud/ui`'s `StatTone`
 * (`packages/schemastud/ui/src/stat-tile.tsx`), a five-member union — `default` · `active` · `busy` ·
 * `warn` · `danger` — each mapped to its own token class; `@schemastud/frame`'s card contract
 * (`src/cards/types.ts`) types the wire field as exactly that union. A producer that invents a sixth
 * name ships a figure the tile renders with NO tone at all, silently.
 *
 * This docblock used to name `positive` and `warning`, which no renderer has ever mapped — the two
 * names a producer reading only this file would have picked. The constants below exist so the set is
 * spelled once, on the class that carries the field, and a producer binds to a constant rather than to
 * a string this file happens to list (realm-dashboards ticket 06a review).
 *
 * `null` is the neutral figure and is NOT `TONE_DEFAULT`: the client distinguishes an absent tone from
 * one explicitly declared default only in that the latter is a deliberate declaration; both render
 * neutral. Producers that mean "no tone" pass null.
 */
#[TypeScript]
class SummaryFigureData extends Data
{
    /** The neutral tone, declared explicitly (null means "no tone declared" and renders the same). */
    public const TONE_DEFAULT = 'default';

    /** A healthy, running, in-good-standing figure. */
    public const TONE_ACTIVE = 'active';

    /** An in-flight figure — work the system is still doing. */
    public const TONE_BUSY = 'busy';

    /** A figure that wants attention but is not a failure. */
    public const TONE_WARN = 'warn';

    /** A failure figure. */
    public const TONE_DANGER = 'danger';

    /**
     * Every tone the reference client renders, in escalation order. A tone outside this set is not an
     * error here — the wire carries it verbatim — but no shipped renderer maps it.
     *
     * @var list<string>
     */
    public const TONES = [self::TONE_DEFAULT, self::TONE_ACTIVE, self::TONE_BUSY, self::TONE_WARN, self::TONE_DANGER];

    /**
     * @param  string  $key  the figure's stable identity within its summary (`total`, `active`, …)
     * @param  string  $label  the figure's display label
     * @param  int|float|string  $value  the figure itself — a count, an amount, or an already-formatted string
     * @param  string|null  $tone  the semantic tone a renderer maps to its own tokens — one of {@see TONES}
     *                             ({@see TONE_DEFAULT}, {@see TONE_ACTIVE}, {@see TONE_BUSY},
     *                             {@see TONE_WARN}, {@see TONE_DANGER}); null = neutral
     */
    public function __construct(
        public string $key,
        public string $label,
        public int|float|string $value,
        public ?string $tone = null,
    ) {}
}
