<?php

namespace Schemastud\Frame\Tests;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Schemastud\Frame\Contracts\FrameResourceHandler;
use Schemastud\Frame\Contracts\FrameResourceHandlerResolver;
use Schemastud\Frame\Contracts\ResourceAccessGate;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

class ResourcePaginationTest extends TestCase
{
    private FrameResourceHandler&MockInterface $handler;

    protected function setUp(): void
    {
        parent::setUp();

        // Exercise the same global input mapper used by the flagship host.
        config()->set('data.name_mapping_strategy.input', CamelCaseMapper::class);

        $this->app->instance(ResourceRegistry::class, (new InMemoryResourceRegistry)->register(
            new ResourceDefinition(
                key: 'articles', model: null, data: SampleResourceData::class,
                creatable: false, query: null, editData: null, policy: null,
                form: 'bare', nav: new NavMetadata(label: 'Articles'),
            ),
        ));
        $this->handler = Mockery::mock(FrameResourceHandler::class);
        $resolver = Mockery::mock(FrameResourceHandlerResolver::class);
        $resolver->shouldReceive('handlerFor')->with('articles')->andReturn($this->handler);
        $this->app->instance(FrameResourceHandlerResolver::class, $resolver);
    }

    public function test_flat_rows_use_the_requested_offset_page(): void
    {
        $this->handler->shouldReceive('index')->once()->andReturn([
            ['id' => 'a'], ['id' => 'b'], ['id' => 'c'], ['id' => 'd'], ['id' => 'e'],
        ]);

        $this->getJson('/frame/resources/articles?page=2&per_page=2')->assertOk()->assertExactJson([
            'data' => [['id' => 'c'], ['id' => 'd']], 'total' => 5, 'page' => 2, 'perPage' => 2,
        ]);
    }

    public function test_an_offset_page_is_not_sliced_a_second_time(): void
    {
        $page = ['data' => [['id' => 'g'], ['id' => 'h']], 'total' => 120, 'page' => 4, 'perPage' => 2];
        $this->handler->shouldReceive('index')->once()->andReturn($page);

        $this->getJson('/frame/resources/articles?page=4&per_page=2')->assertOk()->assertExactJson($page);
    }

    public function test_a_cursor_page_preserves_all_fifty_rows_and_its_continuation(): void
    {
        $rows = array_map(fn (int $id) => ['id' => (string) $id], range(1, 50));
        $page = ['data' => $rows, 'perPage' => 50, 'nextCursor' => 'opaque-next-page'];
        $this->handler->shouldReceive('index')->once()
            ->withArgs(fn ($definition, $params) => $params['per_page'] === '50')
            ->andReturn($page);

        $this->getJson('/frame/resources/articles?per_page=50')->assertOk()->assertExactJson($page);
    }

    public function test_the_response_cursor_can_be_replayed_with_the_same_resource_filters(): void
    {
        $calls = [];
        $this->handler->shouldReceive('index')->andReturnUsing(function ($definition, $params) use (&$calls) {
            $calls[] = $params;

            return isset($params['cursor'])
                ? ['data' => [['id' => 'c']], 'perPage' => 2, 'nextCursor' => null]
                : ['data' => [['id' => 'a'], ['id' => 'b']], 'perPage' => 2, 'nextCursor' => 'opaque+/='];
        });
        $query = ['per_page' => '2', 'filter' => ['author' => 'mine']];
        $first = $this->getJson('/frame/resources/articles?'.http_build_query($query))->assertOk();
        $this->assertSame('opaque+/=', $first->json('nextCursor'));
        $query['cursor'] = $first->json('nextCursor');

        $this->getJson('/frame/resources/articles?'.http_build_query($query))->assertOk()->assertExactJson([
            'data' => [['id' => 'c']], 'perPage' => 2, 'nextCursor' => null,
        ]);
        $this->assertCount(2, $calls);
        $this->assertSame('opaque+/=', $calls[1]['cursor']);
        $this->assertSame(['author' => 'mine'], $calls[1]['filter']);
    }

    public function test_an_empty_terminal_cursor_page_retains_null_without_claiming_a_total(): void
    {
        $page = ['data' => [], 'perPage' => 25, 'nextCursor' => null];
        $this->handler->shouldReceive('index')->once()->andReturn($page);

        $this->getJson('/frame/resources/articles?cursor=last')->assertOk()->assertExactJson($page);
    }

    public function test_generated_page_types_preserve_optional_metadata_and_terminal_null(): void
    {
        $directory = sys_get_temp_dir().'/frame-page-types-'.bin2hex(random_bytes(8));
        mkdir($directory);
        try {
            $logger = new \Spatie\TypeScriptTransformer\Support\Loggers\ArrayLogger;
            \Spatie\TypeScriptTransformer\TypeScriptTransformer::create(
                \Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory::create()
                    ->transformer(\Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer::class)
                    ->transformDirectories(__DIR__.'/../src/Data')
                    ->outputDirectory($directory)
                    ->writer(new \Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter('page.d.ts'))
                    ->withoutManifest(),
                $logger,
            )->execute();
            $output = file_get_contents($directory.'/page.d.ts');
            $this->assertSame([], $logger->messages);
            $this->assertSame(1, preg_match('/export type ResourcePageData = \{(.*?)\};/s', $output, $page));
            $this->assertStringContainsString('perPage: number', $page[1]);
            $this->assertStringContainsString('total?: number', $page[1]);
            $this->assertStringContainsString('page?: number', $page[1]);
            $this->assertStringContainsString('nextCursor?: string | null', $page[1]);
        } finally {
            if (is_file($directory.'/page.d.ts')) {
                unlink($directory.'/page.d.ts');
            }
            rmdir($directory);
        }
    }

    #[DataProvider('invalidPagination')]
    public function test_invalid_pagination_is_refused_before_the_handler(array $query, string $field): void
    {
        $calls = 0;
        $this->handler->shouldReceive('index')->andReturnUsing(function () use (&$calls) {
            $calls++;

            return [];
        });

        $this->getJson('/frame/resources/articles?'.http_build_query($query))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(0, $calls);
    }

    public static function invalidPagination(): array
    {
        return [
            'array cursor' => [['cursor' => ['bad']], 'cursor'],
            'array size' => [['per_page' => [2]], 'per_page'],
            'negative size' => [['per_page' => -1], 'per_page'],
            'oversized page' => [['per_page' => 101], 'per_page'],
            'text size' => [['per_page' => 'bad'], 'per_page'],
            'array page' => [['page' => [2]], 'page'],
            'zero page' => [['page' => 0], 'page'],
        ];
    }

    public function test_denied_resource_access_precedes_pagination_validation_and_execution(): void
    {
        $this->app->instance(ResourceAccessGate::class, new class implements ResourceAccessGate
        {
            public function allowsResource(ResourceDefinition $definition): bool
            {
                return false;
            }
        });
        $this->handler->shouldNotReceive('index');

        $this->getJson('/frame/resources/articles?cursor[]=bad')->assertForbidden();
        $this->getJson('/frame/resources/missing?cursor[]=bad')->assertNotFound();
    }
}
