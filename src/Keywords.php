<?php

namespace Schemastud\Frame;

use Schemastud\Frame\Strategies\ResourceRefStrategy;
use Schemastud\Frame\Strategies\WidgetContextsStrategy;

/**
 * The JSON-Schema extension keywords THIS package owns (the ADR-0074 owner-prefix
 * doctrine: a keyword is legitimate because a package declares it here).
 *
 * The form control is the bare `x-widget` / `x-widget-options` (ADR-0006, which supersedes
 * the keyword clause of the flagship's ADR-0081): it is the keyword seam's form resolves, so a
 * schema needs no bridge. `x-stud-*` keys the schemastud *tier* for the keywords that stay
 * tier-scoped (`x-stud-widget-contexts`, `x-stud-resource-ref`); order/label/help ride free on
 * declaration order / `title` / `description`.
 *
 * Honest caveat (RFC 6648): `x-*` is a pragmatic convention, not a shipped-spec
 * guarantee — JSON-Schema's `x-`-as-annotation is forthcoming, not in 2020-12.
 * Framed like RJSF's reserved `ui:` ("what" vs "how").
 */
class Keywords
{
    /**
     * The property's form control: stamped by `#[Widget]`, or projected from an ordinary
     * (not heavyweight) `#[WidgetIn('edit', …)]` by {@see WidgetContextsStrategy}.
     */
    public const Widget = 'x-widget';

    /** The form control's declared options, beside {@see self::Widget}. */
    public const WidgetOptions = 'x-widget-options';

    /**
     * The per-context widget map projected from the `#[WidgetIn]` family — one
     * property's `{context => entry}` participation across the per-property half of
     * the closed seven-context enum (edit|detail|list-column|row-cell); the class-only
     * contexts (list-item, summary, overview) ride the ContextManifest root instead.
     * Its one reader is server-side: laravel-beam's `CollectionResourceContextNodes`. A
     * client reads the manifest's `byNode`, never this keyword.
     */
    public const WidgetContexts = 'x-stud-widget-contexts';

    /**
     * A property references rows of a registered frame resource — the picker's
     * config bag (resource/value/label/multiple/scope) projected from
     * `#[ResourceRef]` by {@see ResourceRefStrategy}.
     * The frontend ResourceRefWidget reads this to fetch options and render a select.
     */
    public const ResourceRef = 'x-stud-resource-ref';

    /**
     * Every `x-` keyword this package owns / emits.
     *
     * @return list<string>
     */
    public static function owned(): array
    {
        return [self::Widget, self::WidgetOptions, self::WidgetContexts, self::ResourceRef];
    }
}
