<?php

namespace Schemastud\Frame\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One figure on a resource's summary: a keyed, labelled value with an optional semantic tone. */
#[TypeScript]
class SummaryFigureData extends Data
{
    /**
     * @param  string  $key  the figure's stable identity within its summary (`total`, `active`, …)
     * @param  string  $label  the figure's display label
     * @param  int|float|string  $value  the figure itself — a count, an amount, or an already-formatted string
     * @param  string|null  $tone  an optional semantic tone name a renderer maps to its own tokens (`positive`, `warning`, …); null = neutral
     */
    public function __construct(
        public string $key,
        public string $label,
        public int|float|string $value,
        public ?string $tone = null,
    ) {}
}
