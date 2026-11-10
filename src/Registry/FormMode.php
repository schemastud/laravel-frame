<?php

namespace Schemastud\Frame\Registry;

/**
 * A resource's form mode — the one list every producer's `form` value is checked against.
 *
 * - `enriched` (the default): the form draws each node's declared widget.
 * - `bare`: the plain-data mode; the form resolves no declared widget name.
 *
 * Any other value is refused where a definition is built ({@see ResourceDefinition}, and beam's
 * particle registration), never later on the client.
 */
enum FormMode: string
{
    case Enriched = 'enriched';
    case Bare = 'bare';

    /**
     * The mode `$value` names, or an exception naming the resource and the accepted values.
     *
     * @throws \InvalidArgumentException
     */
    public static function fromDeclaration(string $value, string $resource): self
    {
        return self::tryFrom($value) ?? throw new \InvalidArgumentException(sprintf(
            'Resource [%s] declares form mode [%s]; a form mode is one of: %s.',
            $resource,
            $value,
            implode(', ', array_map(static fn (self $mode): string => $mode->value, self::cases())),
        ));
    }
}
