<?php

namespace Schemastud\Frame\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use ReflectionClass;
use Schemastud\DataSchemas\Generators\JsonSchemaGenerator;
use Schemastud\Frame\Contracts\FrameFilterProvider;
use Schemastud\Frame\Contracts\FrameResourceHandlerResolver;
use Schemastud\Frame\Contracts\SavedFilterStore;
use Schemastud\Frame\Registry\AdminResourceRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Frame's resource socket — the uniform machinery that drives `@schemastud/frame`'s
 * ListShell / EditShell over `{prefix}/resources/{resource}` and the schema-driven
 * facets bar over `{prefix}/filter-schema|filter-options|saved-filters`. Generic over
 * a registry of resources; the non-uniform per-resource CRUD is the host's plug,
 * resolved through {@see FrameResourceHandlerResolver}. Persistence-agnostic: whether a
 * row is a plain model or a beam `SchemaRecord` projection is the handler's concern.
 *
 * Envelopes (unchanged from the ported host shape, so any existing JS transport is a
 * straight lift): list → `{data,total,page,perPage}`, single → `{data}`, schema → the
 * raw generated JSON Schema, delete → 204.
 */
class FrameResourceController
{
    public function __construct(
        protected AdminResourceRegistry $registry,
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

    public function schema(Request $request, string $resource): array
    {
        $definition = $this->definition($resource);
        $editClass = $definition->editData ?? $definition->data;

        return (new JsonSchemaGenerator(config('data-schemas', [])))
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
