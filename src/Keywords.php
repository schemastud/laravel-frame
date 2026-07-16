<?php

namespace Schemastud\Frame;

/**
 * The JSON-Schema extension keywords THIS package owns (the ADR-0074 owner-prefix
 * doctrine: a keyword is legitimate because a package declares it here).
 *
 * `x-stud-*` keys the schemastud *tier*, not this package — the presentation vocab
 * is owned by the rung, not bound to `frame`. v1 mints exactly one selection
 * keyword; order/label/help ride free on declaration order / `title` / `description`.
 *
 * Honest caveat (RFC 6648): `x-stud-*` is a pragmatic convention, not a shipped-spec
 * guarantee — JSON-Schema's `x-`-as-annotation is forthcoming, not in 2020-12.
 * Framed like RJSF's reserved `ui:` ("what" vs "how").
 */
class Keywords
{
    public const Widget = 'x-stud-widget';

    public const WidgetOptions = 'x-stud-widget-options';

    /**
     * The per-context widget map projected from the `#[WidgetIn]` family — one
     * property's `{context => entry}` participation across the closed five-context
     * enum (edit|detail|list-column|list-item|row-cell).
     */
    public const WidgetContexts = 'x-stud-widget-contexts';

    /**
     * Every `x-` keyword this package owns / emits.
     *
     * @return list<string>
     */
    public static function owned(): array
    {
        return [self::Widget, self::WidgetOptions, self::WidgetContexts];
    }
}
