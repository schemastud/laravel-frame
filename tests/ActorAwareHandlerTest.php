<?php

namespace Schemastud\Frame\Tests;

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Mockery;
use Schemastud\Frame\Authorization\ResourceAuthorizer;
use Schemastud\Frame\Contracts\ActorAwareFrameResourceHandler;
use Schemastud\Frame\Contracts\FrameResourceHandler;
use Schemastud\Frame\Contracts\FrameResourceHandlerResolver;
use Schemastud\Frame\Http\Controllers\FrameResourceController;
use Schemastud\Frame\Registry\InMemoryResourceRegistry;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;

class ActorAwareHandlerTest extends TestCase
{
    private function exercise(?User $actor, bool $aware, string $verb): void
    {
        $definition = new ResourceDefinition(key: 'sample', model: null, data: SampleResourceData::class, creatable: false, query: null, editData: null, policy: null, form: 'bare', nav: new NavMetadata(label: 'Sample'));
        $registry = (new InMemoryResourceRegistry)->register($definition);
        $handler = $aware
            ? Mockery::mock(FrameResourceHandler::class, ActorAwareFrameResourceHandler::class)
            : Mockery::mock(FrameResourceHandler::class);
        $bound = $aware ? Mockery::mock(FrameResourceHandler::class) : $handler;
        if ($aware) {
            $handler->shouldReceive('forActor')->once()->with($actor, 'account')->andReturn($bound);
        }
        $arguments = match ($verb) {
            'index', 'store' => [$definition, []],
            'update' => [$definition, '42', []],
            default => [$definition, '42'],
        };
        $result = $verb === 'index' ? [] : ['id' => '42'];
        $expectation = $bound->shouldReceive($verb)->once()->with(...$arguments);
        $expectation->andReturn($verb === 'destroy' ? null : $result);
        $resolver = Mockery::mock(FrameResourceHandlerResolver::class);
        $resolver->shouldReceive('handlerFor')->once()->with('sample')->andReturn($handler);
        $authorizer = Mockery::mock(ResourceAuthorizer::class);
        $authorizer->shouldReceive('authorizeAccess')->once()->with($definition);
        if ($verb === 'store') {
            $authorizer->shouldReceive('authorize')->once()->with($definition, 'create');
        } elseif (in_array($verb, ['update', 'destroy'])) {
            $authorizer->shouldReceive('authorize')->once()->with($definition, $verb === 'destroy' ? 'delete' : 'update', '42');
        }
        $request = Request::create('/frame/resources/sample/42');
        $request->setUserResolver(fn () => $actor);
        $route = new Route(['GET'], '/', fn () => null);
        $route->defaults['realm'] = 'account';
        $request->setRouteResolver(fn () => $route);
        $controller = new FrameResourceController($registry, $resolver, $authorizer);
        $response = in_array($verb, ['index', 'store'])
            ? $controller->{$verb}($request, 'sample')
            : $controller->{$verb}($request, 'sample', '42');
        if ($verb === 'destroy') {
            $this->assertSame(204, $response->status());
        } else {
            $this->assertSame($result, $response['data']);
        }
    }

    public function test_controller_binds_explicit_guest_and_realm(): void
    {
        foreach (['index', 'show', 'store', 'update', 'destroy'] as $verb) {
            $this->exercise(null, true, $verb);
        }
    }

    public function test_controller_binds_authenticated_actor_and_realm(): void
    {
        foreach (['index', 'show', 'store', 'update', 'destroy'] as $verb) {
            $this->exercise(new User, true, $verb);
        }
    }

    public function test_legacy_handler_is_called_without_new_requirements(): void
    {
        foreach (['index', 'show', 'store', 'update', 'destroy'] as $verb) {
            $this->exercise(null, false, $verb);
        }
    }
}
