<?php

namespace Schemastud\Frame\Tests;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;
use ReflectionClass;
use Rushing\LaravelDataSchemasScribe\OpenApi\DataSchemaGenerator;
use Rushing\LaravelDataSchemasScribe\Strategies\UseDataQuery;
use Rushing\LaravelDataSchemasScribe\Strategies\UseDataResponse;
use Schemastud\DataSchemas\Generators\Generator;
use Schemastud\Frame\Registry\ResourceDefinition;
use Spatie\TypeScriptTransformer\Support\Loggers\ArrayLogger;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

class FilterContractGenerationTest extends TestCase
{
    public function test_booted_filter_routes_generate_typed_openapi_lists_and_query_parameters(): void
    {
        $endpoints = [];
        foreach (['schema', 'options', 'variants'] as $method) {
            $route = $this->app['router']->getRoutes()->getByName('frame.resources.filters.'.$method);
            $this->assertNotNull($route);
            $extracted = ExtractedEndpointData::fromRoute($route);
            $responses = (new UseDataResponse(new DocumentationConfig([])))($extracted);
            $this->assertCount(1, $responses);
            $query = (new UseDataQuery(new DocumentationConfig([])))($extracted);
            if ($method === 'options') {
                $this->assertSame('string', $query['search']['type']);
                $this->assertFalse($query['search']['required']);
            }
            $endpoints[$method] = OutputEndpointData::create([
                'httpMethods' => ['GET'], 'uri' => $route->uri(), 'custom' => $extracted->custom,
            ]);
        }

        $groups = [['description' => '', 'name' => 'Frame filters', 'endpoints' => array_values($endpoints)]];
        $generator = new DataSchemaGenerator(new DocumentationConfig([]));
        $root = $generator->root([], $groups);
        $schemas = [];
        foreach ($endpoints as $method => $endpoint) {
            $operation = $generator->pathItem(['responses' => ['200' => []]], $groups, $endpoint);
            $schemas[$method] = $operation['responses']['200']['content']['application/json']['schema'];
        }

        $this->assertSame('object', $schemas['schema']['properties']['data']['type']);
        $this->assertSame(['string', 'null'], $schemas['schema']['properties']['savedViewsResource']['type']);
        $this->assertSame('#/components/schemas/FilterOptionData', $schemas['options']['properties']['data']['items']['$ref'] ?? null);
        $this->assertSame('string', $root['components']['schemas']['FilterOptionData']['properties']['value']['type']);
        $this->assertSame('string', $root['components']['schemas']['FilterOptionData']['properties']['label']['type']);
        $this->assertSame('#/components/schemas/FilterVariantsData', $schemas['variants']['properties']['data']['$ref']);
        $this->assertSame('#/components/schemas/FilterVariantData', $root['components']['schemas']['FilterVariantsData']['properties']['variants']['items']['$ref'] ?? null);
        $variant = $root['components']['schemas']['FilterVariantData']['properties'];
        $this->assertSame('string', $variant['resource']['type']);
        $this->assertSame('boolean', $variant['canonical']['type']);
        $this->assertSame('boolean', $variant['sameAsCanonical']['type']);
        $this->assertSame('boolean', $root['components']['schemas']['ResourceCapabilitiesData']['properties']['create']['type']);
        $this->assertSame('boolean', $root['components']['schemas']['ResourceCapabilitiesData']['properties']['delete']['type']);
    }

    public function test_resource_definition_schema_does_not_advertise_the_server_provider(): void
    {
        $schema = app(Generator::class)->forResponse()->generate(new ReflectionClass(ResourceDefinition::class));
        $this->assertArrayHasKey('key', $schema['properties']);
        $this->assertArrayNotHasKey('filterProvider', $schema['properties']);
        $this->assertNotContains('filterProvider', $schema['required']);
        $this->assertArrayNotHasKey('summaryProvider', $schema['properties']);
        $this->assertNotContains('summaryProvider', $schema['required']);

        // ADR-0004: the server-side inputs are absent from the response schema as well as the wire. Before
        // it, `model` was hidden from the JSON but still advertised here as a REQUIRED property.
        foreach (['model', 'query', 'policy'] as $serverSide) {
            $this->assertArrayNotHasKey($serverSide, $schema['properties'], $serverSide);
            $this->assertNotContains($serverSide, $schema['required'], $serverSide);
        }
        $this->assertArrayHasKey('editData', $schema['properties']);
    }

    public function test_the_booted_summary_route_generates_a_typed_openapi_response(): void
    {
        $route = $this->app['router']->getRoutes()->getByName('frame.resources.summary');
        $this->assertNotNull($route);
        $extracted = ExtractedEndpointData::fromRoute($route);
        $this->assertCount(1, (new UseDataResponse(new DocumentationConfig([])))($extracted));
        $endpoint = OutputEndpointData::create([
            'httpMethods' => ['GET'], 'uri' => $route->uri(), 'custom' => $extracted->custom,
        ]);
        $groups = [['description' => '', 'name' => 'Frame summary', 'endpoints' => [$endpoint]]];
        $generator = new DataSchemaGenerator(new DocumentationConfig([]));
        $root = $generator->root([], $groups);
        $operation = $generator->pathItem(['responses' => ['200' => []]], $groups, $endpoint);
        $schema = $operation['responses']['200']['content']['application/json']['schema'];

        $this->assertSame('string', $schema['properties']['key']['type']);
        $this->assertSame(['string', 'null'], $schema['properties']['icon']['type']);
        $this->assertSame('#/components/schemas/SummaryFigureData', $schema['properties']['figures']['items']['$ref'] ?? null);
        $figure = $root['components']['schemas']['SummaryFigureData']['properties'];
        $this->assertSame('string', $figure['key']['type']);
        $this->assertSame(['string', 'null'], $figure['tone']['type']);
        $this->assertArrayNotHasKey('href', $schema['properties']);

        // `overview` is a DECLARED shape, not a `mixed` bag: it must reach the spec as a named component.
        $this->assertStringContainsString('OverviewData', json_encode($schema['properties']['overview']));
        $overview = $root['components']['schemas']['OverviewData']['properties'];
        $this->assertStringContainsString('SummaryFigureData', json_encode($overview['headline']));
        $this->assertSame('array', $overview['items']['type']);
        $this->assertSame(['string', 'null'], $overview['period']['type']);
    }

    public function test_generated_typescript_retains_list_members_and_hides_the_server_provider(): void
    {
        $directory = sys_get_temp_dir().'/frame-filter-types-'.bin2hex(random_bytes(8));
        mkdir($directory);
        try {
            $logger = new ArrayLogger;
            TypeScriptTransformer::create(TypeScriptTransformerConfigFactory::create()
                ->transformer(AttributedClassTransformer::class)
                ->transformDirectories(__DIR__.'/../src/Data', __DIR__.'/../src/Registry')
                ->outputDirectory($directory)
                ->writer(new GlobalNamespaceWriter('frame.d.ts'))
                ->withoutManifest(), $logger)->execute();
            $output = file_get_contents($directory.'/frame.d.ts');
            $this->assertSame([], $logger->messages, 'Generated references must all resolve.');
            $this->assertStringContainsString('data: Schemastud.Frame.Data.FilterOptionData[]', $output);
            $this->assertStringContainsString('variants: Schemastud.Frame.Data.FilterVariantData[]', $output);
            $this->assertStringContainsString('data: Schemastud.Frame.Data.FilterVariantsData', $output);
            $this->assertStringContainsString('savedViewsResource: string | null', $output);
            $this->assertStringContainsString('savedViewsCan: Schemastud.Frame.Data.ResourceCapabilitiesData | null', $output);
            $this->assertSame(1, preg_match('/export type ResourceDefinition = \{(.*?)\};/s', $output, $definition));
            $this->assertStringContainsString('key: string', $definition[1]);
            $this->assertStringNotContainsString('filterProvider', $definition[1]);
            $this->assertStringNotContainsString('summaryProvider', $definition[1]);
            $this->assertStringNotContainsString('model:', $definition[1]);
            $this->assertStringNotContainsString('query:', $definition[1]);
            $this->assertStringNotContainsString('policy:', $definition[1]);
            $this->assertStringContainsString('editData: string | null', $definition[1]);
            $this->assertStringContainsString('figures: Schemastud.Frame.Data.SummaryFigureData[]', $output);
            $this->assertStringContainsString('overview: Schemastud.Frame.Data.OverviewData | null', $output);
            $this->assertSame(1, preg_match('/export type OverviewData = \{(.*?)\};/s', $output, $overview));
            $this->assertStringContainsString('headline: Schemastud.Frame.Data.SummaryFigureData | null', $overview[1]);
            $this->assertStringContainsString('period: string | null', $overview[1]);
        } finally {
            if (is_file($directory.'/frame.d.ts')) {
                unlink($directory.'/frame.d.ts');
            }
            rmdir($directory);
        }
    }
}
