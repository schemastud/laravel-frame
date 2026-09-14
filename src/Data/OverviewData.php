<?php

namespace Schemastud\Frame\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The expanded half of a resource summary — what a dashboard tile shows when it is given more room than a
 * row of figures.
 *
 * It exists because `overview` was `mixed`: an undeclared shape on a declared wire, which is the one thing
 * every boundary payload here is not allowed to be. A provider that has nothing to expand omits it and the
 * summary carries `null`.
 *
 * `$items` is deliberately a bag of ALREADY-DECLARED rows rather than a second row contract: they are
 * records of the addressed resource, declared on that resource's own read wire and rendered through its
 * `list-item` binding. Frame re-declaring their shape here would be a second spelling of a contract the
 * resource already owns.
 */
#[TypeScript]
class OverviewData extends Data
{
    /**
     * @param  SummaryFigureData|null  $headline  the one big figure the overview leads with; null when the figures row says it all
     * @param  list<array<string, mixed>>  $items  records of the addressed resource, in display order — each already declared on that resource's read wire and rendered through its `list-item` binding
     * @param  string|null  $period  the human period the overview covers ("Last 30 days"), when it covers one
     * @param  string|null  $note  a short caption a renderer may show beneath the overview
     */
    public function __construct(
        public ?SummaryFigureData $headline = null,
        /** @var list<array<string, mixed>> */
        public array $items = [],
        public ?string $period = null,
        public ?string $note = null,
    ) {}
}
