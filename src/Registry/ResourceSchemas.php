<?php

namespace Schemastud\Frame\Registry;

use InvalidArgumentException;
use ReflectionClass;
use Schemastud\DataSchemas\Generators\Generator;
use Schemastud\Frame\Http\Controllers\FrameResourceController;
use Schemastud\Frame\Vocabulary\DeclaredWidgets;

/**
 * The schema endpoint's choice of Data class and generator, as one method that both the endpoint
 * ({@see FrameResourceController::schema()} and `actionSchema()`) and the declaration walker
 * ({@see DeclaredWidgets}) call, so the walker reads exactly what frame would serve (ticket 05, ruling 1).
 *
 * The class: `write` reflects `editData ?? data` in the generator's request mode; `read` reflects `data` in its
 * response mode; an action's form reflects its `input` in request mode. The generator is the HOST'S CONFIGURED chain,
 * `app(Generator::class)`, never a hand-built member: dispatch ("the first member whose `canGenerate()` accepts this
 * class") lives only in the chain (see the controller's note on `~/Herd/thingsontv`).
 *
 * A {@see \Schemastud\Frame\Contracts\ResourceSchemaResolver} answers per request and stays in the controller: it
 * needs the request, and a schema it returns is not walked (ruling 1, "What would miss").
 */
class ResourceSchemas
{
    public const PurposeWrite = 'write';

    public const PurposeRead = 'read';

    public function __construct(
        protected Generator $generator,
    ) {}

    /**
     * The Data class the endpoint projects for a purpose.
     *
     * @param  'write'|'read'  $purpose
     * @return class-string
     */
    public function dataClass(ResourceDefinition $definition, string $purpose = self::PurposeWrite): string
    {
        return match ($purpose) {
            self::PurposeRead => $definition->data,
            self::PurposeWrite => $definition->editData ?? $definition->data,
            default => throw new InvalidArgumentException("Unknown schema purpose [{$purpose}]; expected write or read."),
        };
    }

    /**
     * The resource's schema for a purpose, as the endpoint serves it.
     *
     * @param  'write'|'read'  $purpose
     * @return array<string, mixed>
     */
    public function forResource(ResourceDefinition $definition, string $purpose = self::PurposeWrite): array
    {
        $generator = $purpose === self::PurposeRead ? $this->generator->forResponse() : $this->generator->forRequest();

        return $generator->generate(new ReflectionClass($this->dataClass($definition, $purpose)));
    }

    /**
     * One declared action's form schema: its `input` in request mode. The caller checks the action has one.
     *
     * @return array<string, mixed>
     */
    public function forActionInput(ResourceActionDefinition $action): array
    {
        if ($action->input === null) {
            throw new InvalidArgumentException("Action [{$action->key}] is confirm-only: it declares no input.");
        }

        return $this->generator->forRequest()->generate(new ReflectionClass($action->input));
    }
}
