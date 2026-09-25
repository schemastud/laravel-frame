<?php

namespace Schemastud\Frame\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use ReflectionClass;
use Rushing\LaravelDataSchemasScribe\Attributes\QueryFromData;
use Rushing\LaravelDataSchemasScribe\Attributes\ResponseFromData;
use Schemastud\DataSchemas\Generators\Generator;
use Schemastud\Frame\Authorization\ResourceAuthorizer;
use Schemastud\Frame\Contracts\FrameResourceHandlerResolver;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Data\ResourcePageData;
use Schemastud\Frame\Data\ResourceQueryData;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Frame's resource socket — the uniform machinery that drives `@schemastud/frame`'s
 * ListShell / EditShell over `{prefix}/resources/{resource}` and the schema-driven
 * facets bar through the resource capability controller. Generic over
 * a registry of resources; the non-uniform per-resource CRUD is the host's plug,
 * resolved through {@see FrameResourceHandlerResolver}. Persistence-agnostic: whether a
 * row is a plain model or a beam particle projection is the handler's concern.
 *
 * List pages are declared ResourcePageData: offset pages carry total/page, cursor pages
 * carry nextCursor (including null at the end). Single → `{data}`, schema → the raw
 * generated JSON Schema, delete → 204.
 *
 * ## The write axis is gated here, and the read axis deliberately is not
 *
 * Until 2026-09-05 this class contained no `authorize`, no `Gate::` and no ability name at all, so
 * every verb it served was open to anyone the host's `frame.middleware` let through. At `~/Herd/beam`
 * that is `['web','auth']`, so a member holding only `beam-ux-entry.view` could create and delete
 * every record of every frame resource; `POST` with an empty body answered **422**, proving that
 * validation ran before any permission question was asked, because none was. `store`/`update`/
 * `destroy` now pass through {@see ResourceAuthorizer} FIRST — before `$request->all()` reaches a
 * handler, so an unauthorized write is a 403 and never a validation error.
 *
 * `index`/`show`/`schema` and the facets endpoints take no per-verb POLICY question, and still do
 * not. The read posture is decided elsewhere and differently on purpose (see
 * {@see ResourceAuthorizer}'s docblock); making the two axes symmetric here would close resources
 * whose index is gated by its row-level scope rather than by a class policy, which ADR-0156 §83
 * makes the deliberate design for a filterable resource.
 *
 * ## The REACH axis is above both, and every verb passes through it
 *
 * ⚠️ "The read posture is decided elsewhere" was measured on 2026-09-12 and *elsewhere* was nowhere.
 * At `https://fresh-tower.test`, `demo-member` — an ordinary tenant user — read
 * `/frame/resources/users`, `/frame/resources/teams` and `/frame/resources/tenants` with **200**:
 * every user's email, every team, and the tenant roster with its owner email. The host had placed
 * `users` and `teams` in the operator realm via `config('frame.realms')`, and that list turned out
 * to drive only which links were DRAWN. One socket serves every realm, so `frame.middleware` — one
 * ambient list — could not refuse them without refusing the tenant realm's resources too.
 *
 * {@see definition()} now asks {@see ResourceAuthorizer::authorizeAccess()} before returning, so
 * every CRUD verb here passes the reach question. FrameResourceFiltersController applies the same
 * question before invoking a resource filter provider. Frame does not answer that question: {@see \Schemastud\Frame\Contracts\ResourceAccessGate}
 * is the port, frame's shipped default permits, and the producer that owns realms binds the
 * refusing answer.
 */
class FrameResourceController
{
    public function __construct(
        protected ResourceRegistry $registry,
        protected FrameResourceHandlerResolver $resources,
        protected ResourceAuthorizer $authorizer,
    ) {}

    #[QueryFromData(ResourceQueryData::class)]
    #[ResponseFromData(ResourcePageData::class)]
    public function index(Request $request, string $resource): array
    {
        $definition = $this->definition($resource);
        ResourceQueryData::validateAndCreate($request->query());
        $result = $this->resources->handlerFor($resource)->index($definition, $request->query());

        // A null continuation is still a cursor page. Never paginate either page form twice.
        if (array_key_exists('nextCursor', $result)) {
            return ResourcePageData::cursor($result['data'], $result['perPage'], $result['nextCursor'])->toArray();
        }
        if (isset($result['data']) && array_key_exists('total', $result)) {
            return ResourcePageData::offset($result['data'], $result['total'], $result['page'], $result['perPage'])->toArray();
        }

        return $this->paginate($result, $request);
    }

    /**
     * Get Resource Schema
     *
     * The schema used to edit this resource.
     */
    public function schema(Request $request, string $resource): array
    {
        /*
         * The edit form's contract, projected through the HOST'S CONFIGURED generator chain.
         *
         * Was `new JsonSchemaGenerator(config('data-schemas', []))`. That construction was already
         * correct on CONFIG; what it could not do is DISPATCH. `data-schemas.generators` is a LIST, and
         * the rule "the first member whose `canGenerate()` accepts this class" lives only inside
         * {@see \Schemastud\DataSchemas\Generators\ChainedGenerator} — so hand-building the default
         * member is hand-picking it.
         *
         * That is not hypothetical for this controller. `~/Herd/thingsontv` configures
         * `[BlockJsonSchemaGenerator, JsonSchemaGenerator]` and installs this package, and
         * `BlockJsonSchemaGenerator::canGenerate()` is `isSubclassOf(Block::class)` — a `Block` IS a
         * `Data`, so a Block-backed resource satisfies the plain generator too. The old code therefore
         * ran the WRONG member and silently dropped the block's `#[NodeType]`/`#[NodeAttr]` bridging:
         * an edit form missing the attributes it is supposed to edit, behind an HTTP 200.
         *
         * **UNGUARDED, deliberately** — the same call `splicewire/tower`'s `Api\V1\FragmentController`
         * makes, and the opposite of the one its `CompositionProfileController` makes (that one guards,
         * because there the schema is one field of a list response). The chain THROWS when no
         * configured member accepts the class, where the hand-built generator generated regardless; but
         * this endpoint's ENTIRE product is that one schema, returned raw as the response body. There is
         * nothing to degrade to. A `canGenerate()` guard could only turn the throw into an empty
         * document, which frame's EditShell renders as a form with no fields — the silently-wrong
         * outcome this migration exists to remove, and worse than a 500 because a user can save it.
         * `ChainedGenerator`'s exception already names the class and every configured generator, so the
         * 500 is diagnostic, and its blast radius is one request rather than boot.
         *
         * The method NAME is load-bearing and unchanged: Wayfinder generates `export const schema` from
         * it in 12 hosts. That constrains the signature, not the body — it has no bearing on the guard
         * decision either way.
         */
        $definition = $this->definition($resource);
        $editClass = $definition->editData ?? $definition->data;

        return app(Generator::class)
            ->forRequest()
            ->generate(new ReflectionClass($editClass));
    }

    public function show(Request $request, string $resource, string $id): array
    {
        $definition = $this->definition($resource);

        return ['data' => $this->resources->handlerFor($resource)->show($definition, $id)];
    }

    public function store(Request $request, string $resource): array
    {
        $definition = $this->definition($resource);
        $this->authorizer->authorize($definition, 'create');

        return ['data' => $this->resources->handlerFor($resource)->store($definition, $request->all())];
    }

    public function update(Request $request, string $resource, string $id): array
    {
        $definition = $this->definition($resource);
        $this->authorizer->authorize($definition, 'update', $id);

        return ['data' => $this->resources->handlerFor($resource)->update($definition, $id, $request->all())];
    }

    /**
     * Delete Resource
     *
     * Delete one record from this resource.
     */
    public function destroy(Request $request, string $resource, string $id): Response
    {
        /*
         * ⚠️ `destroy` was the widest of the three, and not only because this controller asked nothing.
         * Beam's handler routes `store`/`update` through a {@see \Splicewire\Beam\Write\ParticleWriter}
         * whose chain opens with an `AuthorizeStage`, so a resource that declared a `policy` string had
         * SOMETHING checking it there — but `ParticleFrameResourceHandler::destroy()` bypasses the writer
         * entirely (`$this->query($definition)->findOrFail($id)->delete()`), so delete had no gate on any
         * path even for a resource that declared one.
         */
        $definition = $this->definition($resource);
        $this->authorizer->authorize($definition, 'delete', $id);

        $this->resources->handlerFor($resource)->destroy($definition, $id);

        return response()->noContent();
    }

    /**
     * Resolve a registered definition — and refuse a principal who may not address it at all.
     *
     * Every verb this controller serves goes through here, which is the point: the REACH question is
     * asked once, in the one place all seven read and write entry points already share, so a verb
     * added later cannot forget it. {@see ResourceAuthorizer::authorizeAccess()} carries the
     * evidence; the short version is that the read verbs were never gated and `frame.middleware`
     * structurally cannot gate them per resource.
     *
     * ORDER is load-bearing. 404 for "not registered here" comes first, so a gated resource and an
     * unknown key stay distinguishable; the 403 then lands before `$request->all()`, before
     * validation, and before any handler is resolved.
     */
    /**
     * The form contract of one declared ACTION (ADR-0005) — its `input` Data class, reflected through the
     * host's configured generator chain in REQUEST mode exactly as {@see schema()} reflects `editData`.
     *
     * The wire carries the input's generated type NAME (ADR-0004), which a client cannot reflect; this is
     * where the schema that name describes is fetched. It is gated by the same resource-access question as
     * every other read here, and not by the per-actor action answer: seeing a form's fields grants nothing,
     * and the action's own URL refuses the send. A confirm-only action (no input) has no form, and an
     * unknown key is not an action of this resource — both answer 404.
     */
    public function actionSchema(Request $request, string $resource, string $action): array
    {
        $definition = $this->definition($resource);
        $declared = $definition->action($action);

        if ($declared === null || $declared->input === null) {
            throw new NotFoundHttpException("The '{$resource}' frame resource declares no action '{$action}' with a form.");
        }

        return app(Generator::class)
            ->forRequest()
            ->generate(new ReflectionClass($declared->input));
    }

    protected function definition(string $resource)
    {
        if (! $this->registry->has($resource)) {
            throw new NotFoundHttpException("Unknown frame resource '{$resource}'.");
        }

        $definition = $this->registry->get($resource);

        $this->authorizer->authorizeAccess($definition);

        return $definition;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, perPage: int}
     */
    protected function paginate(array $rows, Request $request): array
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));
        $total = count($rows);

        return ResourcePageData::offset(
            array_slice($rows, ($page - 1) * $perPage, $perPage), $total, $page, $perPage,
        )->toArray();
    }
}
