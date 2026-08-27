<?php

namespace Schemastud\Frame\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use ReflectionClass;
use Schemastud\DataSchemas\Generators\Generator;
use Schemastud\Frame\Contracts\FrameFilterProvider;
use Schemastud\Frame\Contracts\FrameResourceHandlerResolver;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Contracts\SavedFilterStore;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Frame's resource socket — the uniform machinery that drives `@schemastud/frame`'s
 * ListShell / EditShell over `{prefix}/resources/{resource}` and the schema-driven
 * facets bar over `{prefix}/filter-schema|filter-options|saved-filters`. Generic over
 * a registry of resources; the non-uniform per-resource CRUD is the host's plug,
 * resolved through {@see FrameResourceHandlerResolver}. Persistence-agnostic: whether a
 * row is a plain model or a beam particle projection is the handler's concern.
 *
 * Envelopes (unchanged from the ported host shape, so any existing JS transport is a
 * straight lift): list → `{data,total,page,perPage}`, single → `{data}`, schema → the
 * raw generated JSON Schema, delete → 204.
 */
class FrameResourceController
{
    public function __construct(
        protected ResourceRegistry $registry,
        protected FrameResourceHandlerResolver $resources,
        protected FrameFilterProvider $filters,
    ) {}

    public function index(Request $request, string $resource): array
    {
        $definition = $this->definition($resource);
        $result = $this->resources->handlerFor($resource)->index($definition, $request->query());

        // A handler may pre-paginate; otherwise wrap the flat list here.
        if (isset($result['data']) && array_key_exists('total', $result)) {
            return $result;
        }

        return $this->paginate($result, $request);
    }

    /**
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
    public function schema(Request $request, string $resource): array
    {
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

        return ['data' => $this->resources->handlerFor($resource)->store($definition, $request->all())];
    }

    public function update(Request $request, string $resource, string $id): array
    {
        $definition = $this->definition($resource);

        return ['data' => $this->resources->handlerFor($resource)->update($definition, $id, $request->all())];
    }

    public function destroy(Request $request, string $resource, string $id): Response
    {
        $definition = $this->definition($resource);
        $this->resources->handlerFor($resource)->destroy($definition, $id);

        return response()->noContent();
    }

    // ---- facets endpoints (schema-driven filter bar) ---------------------------

    public function filterSchema(string $resource): array
    {
        return ['data' => $this->filters->for($resource)];
    }

    public function filterOptions(string $ref): array
    {
        return ['data' => $this->filters->options($ref)];
    }

    /**
     * Saved views delegate to a bound {@see SavedFilterStore} when the host provides
     * one; otherwise the read answers empty and the write echoes a transient view (never
     * persisted) so the facets SavedViews affordance mounts and acts without erroring.
     */
    public function savedFilters(Request $request): array
    {
        $store = $this->savedFilterStore();
        $resource = (string) $request->query('resource', '');

        return ['data' => $store ? $store->all($resource) : []];
    }

    public function saveFilter(Request $request): array
    {
        $resource = (string) $request->input('resource', '');
        $payload = [
            'name' => (string) $request->input('name', ''),
            'query_parameters' => (array) $request->input('query_parameters', []),
        ];

        if ($store = $this->savedFilterStore()) {
            return ['data' => $store->save($resource, $payload)];
        }

        return ['data' => [
            'id' => (string) Str::uuid(),
            'name' => $payload['name'],
            'resource' => $resource,
            'query_parameters' => $payload['query_parameters'],
            'visibility' => 'private',
            'is_default' => false,
        ]];
    }

    public function deleteSavedFilter(string $id): Response
    {
        $this->savedFilterStore()?->delete($id);

        return response()->noContent();
    }

    // ---------------------------------------------------------------------------

    protected function savedFilterStore(): ?SavedFilterStore
    {
        return app()->bound(SavedFilterStore::class) ? app(SavedFilterStore::class) : null;
    }

    protected function definition(string $resource)
    {
        if (! $this->registry->has($resource)) {
            throw new NotFoundHttpException("Unknown frame resource '{$resource}'.");
        }

        return $this->registry->get($resource);
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

        return [
            'data' => array_values(array_slice($rows, ($page - 1) * $perPage, $perPage)),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }
}
