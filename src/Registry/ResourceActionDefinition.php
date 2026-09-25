<?php

namespace Schemastud\Frame\Registry;

use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One ACTION a framed resource offers beyond its CRUD verbs — a button that sends a request to a URL
 * the producer names, optionally with a form, and presents the answer (ADR-0005).
 *
 * Frame owns the generic concept and nothing about where it came from. A producer (beam projects its
 * opted-in `#[ParticleOp]`s onto this) reflects its own declaration and hands frame one of these per
 * action; frame renders the button, the form and the result, and never names the operation system
 * behind the URL. So there is no operation class, handler, ability or policy here:
 *
 *  - WHO may press it is answered server-side by the {@see \Schemastud\Frame\Contracts\ResourceActionAuthorizer}
 *    port and rides the per-actor `can.actions` map on the {@see ContextManifest}. The URL's own mount is
 *    the enforcement; the map only decides whether the button is drawn.
 *  - WHAT the form looks like is `$input`, a Data class. Per ADR-0004 it is a class-string in PHP (the
 *    action schema endpoint reflects it) and the generated type's dot-form name on the wire.
 *
 * @see ResourceDefinition::$actions
 */
#[TypeScript]
class ResourceActionDefinition extends Data
{
    /** The action sits beside the list's "New" and addresses the collection. */
    public const ScopeResource = 'resource';

    /** The action sits on each row and on the detail page and addresses one record. */
    public const ScopeRecord = 'record';

    /** Show the response's message, then refetch the resource's reads. The default. */
    public const ResultToast = 'toast';

    /** Open the resulting record's detail (the response's `data.id`, else the acted-on record). */
    public const ResultNavigate = 'navigate';

    /** The placeholder a record-scope URL template carries for the record's key. */
    public const IdPlaceholder = '{id}';

    /**
     * @param  string  $key  unique within the resource; the `can.actions` key and the schema endpoint's `{action}` segment
     * @param  string  $label  the button's words
     * @param  'resource'|'record'  $scope  where the button renders (see the constants)
     * @param  string  $method  the HTTP verb the URL answers to
     * @param  string  $url  the URL to send the request to. A `record` action's template carries `{id}`; a `resource` action's carries none
     * @param  class-string<Data>|null  $input  the Data class the request body must satisfy, or null for a confirm-only action that sends no body. Class-string in PHP; on the wire, the generated type's dot-form name (ADR-0004)
     * @param  'toast'|'navigate'  $result  how the answer is presented (see the constants)
     * @param  bool  $destructive  whether the button reads as dangerous and a confirm-only press asks first in those words
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $scope,
        public string $method,
        public string $url,
        #[WithTransformer(GeneratedTypeName::class)]
        public ?string $input = null,
        public string $result = self::ResultToast,
        public bool $destructive = false,
    ) {}

    /** Whether this action renders a form before it sends — false means a confirm-only button. */
    public function hasInput(): bool
    {
        return $this->input !== null;
    }
}
