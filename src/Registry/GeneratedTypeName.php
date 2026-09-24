<?php

namespace Schemastud\Frame\Registry;

use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * Projects a Data class-string onto the wire as the name of its GENERATED type — the class's real
 * native namespace with dots for backslashes (`Schemastud.Frame.Tests.Fixtures.SampleResourceData`),
 * which is the exact identifier `typescript:transform` emits and therefore the one a manifest consumer
 * already holds. It runs at transform time only: the property a producer reads server-side is still the
 * class-string, so `ContextManifest::forResource()` and the schema endpoint reflect the class as before.
 *
 * It is applied to every Data-class slot on {@see ResourceDefinition} that reaches the wire — `data`,
 * `editData` and `createResultData` (ADR-0002, ADR-0004) — so the manifest spells a class one way only.
 * Class-string slots that are NOT Data classes (`model`, `query`, `policy`, the two providers) are not
 * renamed: they are hidden from the wire outright, because a browser has nothing to resolve them against.
 *
 * Why a type name and not a schema reference: the manifest describes a resource's LIST rows, and the one
 * schema frame serves per resource (`GET /frame/resources/{key}/schema`) is the EDIT shape
 * (`editData ?? data`) — a reference to it would name a different thing. The generated type name is
 * derivable with no I/O and no second registry, and the naming rule is already settled one rung up
 * (beam's particle doctrine: "type names are the class's real native namespace, dots for backslashes").
 * Decided in ADR-0002.
 *
 * A non-string, or a string carrying no backslash (a host already passing a bare name), passes through
 * untouched: this transformer only ever removes the one thing a browser has no use for.
 */
final class GeneratedTypeName implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return str_replace('\\', '.', ltrim($value, '\\'));
    }
}
