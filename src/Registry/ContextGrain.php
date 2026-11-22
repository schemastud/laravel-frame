<?php

namespace Schemastud\Frame\Registry;

/**
 * The subject a render context draws (frame-foundation 86, ruling item 1): one property, one whole record, or the whole
 * collection. The grain decides where a `#[WidgetIn]` may be declared: a property-grain context on a property (and, at
 * the class level, only for a widget its entry reserves), a record- or collection-grain context only on the class,
 * because no single property can stand for the record or the collection.
 */
enum ContextGrain: string
{
    case Property = 'property';
    case Record = 'record';
    case Collection = 'collection';

    /** The grain's name in a refusal: "per-property", "whole-record" or "whole-collection". */
    public function describe(): string
    {
        return match ($this) {
            self::Property => 'per-property',
            self::Record => 'whole-record',
            self::Collection => 'whole-collection',
        };
    }
}
