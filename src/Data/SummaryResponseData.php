<?php

namespace Schemastud\Frame\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The answer at `GET resources/{resource}/summary`: a resource compressed to figures, plus an optional
 * expanded `overview` payload.
 *
 * Deliberately no `href`. Frame's resource socket is realm-blind — it does not know which realm's page a
 * summary is drawn on — so a link is stamped by whichever realm-aware producer lays the summary out, never
 * carried here.
 */
#[TypeScript]
class SummaryResponseData extends Data
{
    /**
     * @param  string  $key  the summarized resource's key
     * @param  string  $label  the resource's display label
     * @param  string|null  $icon  the resource's declared nav icon, when it has one
     * @param  list<SummaryFigureData>  $figures  the figures, in display order
     * @param  OverviewData|null  $overview  the expanded overview payload, when the provider offers one; null when it offers only figures
     */
    public function __construct(
        public string $key,
        public string $label,
        public ?string $icon,
        #[DataCollectionOf(SummaryFigureData::class)]
        public array $figures,
        public ?OverviewData $overview = null,
    ) {}
}
