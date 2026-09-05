<?php

namespace Schemastud\Frame\Tests;

use Schemastud\Frame\Contracts\FrameNavContributor;
use Schemastud\Frame\Registry\NavManifest;
use Schemastud\Frame\Registry\RouteContextEntry;

/**
 * The `nav` / `routeContext` half of `/frame/manifest` — frame's OPTIONAL plug seam for the
 * router-and-navigation projection, the sibling of {@see \Schemastud\Frame\Contracts\ResourceContextContributor}.
 *
 * Until this seam existed, exactly ONE host in the estate emitted `nav` and `routeContext`, and it
 * did so because it had written its own copy of frame's manifest controller. The projection is now
 * a package concern; the LIST it projects stays host-owned (api-surface-coherence 142 — *realm →
 * host-side list*, 141 — *SPELL IT OUT*).
 *
 * ⚠️ The fixture below answers DIFFERENTLY per realm on purpose. A fixture that returned one shape
 * for every realm would let a test that asserts nothing about realm routing still pass, which is
 * this estate's signature defect (AGENTS.md §*The estate's signature defect: an instrument that
 * reports success by not running*).
 */
class NavManifestTest extends TestCase
{
    public function test_an_unbound_host_gets_exactly_the_two_keys_it_always_got(): void
    {
        $response = $this->getJson('/frame/manifest');

        $response->assertOk();

        $this->assertSame(
            ['resources', 'contexts'],
            array_keys($response->json()),
            'A host that binds no contributor must get a byte-identical payload — the plug is additive or it is a BC break.'
        );
    }

    public function test_a_bound_contributor_adds_nav_and_route_context(): void
    {
        $this->app->instance(FrameNavContributor::class, new FixtureNavContributor);

        $response = $this->getJson('/frame/manifest');

        $response->assertOk();

        $this->assertSame(
            ['resources', 'contexts', 'nav', 'routeContext'],
            array_keys($response->json())
        );
        $this->assertSame('nav-for-', $response->json('nav.items.0.label'));
        $this->assertSame('leaf', $response->json('routeContext.0.routeName'));
    }

    public function test_the_contributor_is_asked_for_the_realm_the_matched_route_declares(): void
    {
        $contributor = new FixtureNavContributor;
        $this->app->instance(FrameNavContributor::class, $contributor);

        // Two realms, two answers. The route's `realm` default is the only thing that differs, so a
        // manifest that ignored it would return the same tree twice and this assertion would fail.
        $this->assertSame('nav-for-operator', $this->manifestNavLabelFor('operator'));
        $this->assertSame('nav-for-tenant', $this->manifestNavLabelFor('tenant'));

        $this->assertSame(['operator', 'tenant'], $contributor->asked);
    }

    public function test_a_contributor_that_declines_this_realm_leaves_the_payload_alone(): void
    {
        $this->app->instance(FrameNavContributor::class, new FixtureNavContributor(declines: ['nowhere']));

        $manifest = app(NavManifest::class)->forRealm('nowhere');

        $this->assertSame([], $manifest, 'Declining is how a contributor says "this host has no navigation here" without failing the manifest.');
    }

    public function test_no_contributor_bound_is_the_normal_case_and_needs_no_container(): void
    {
        $this->assertSame([], (new NavManifest)->forRealm('tenant'));
    }

    private function manifestNavLabelFor(string $realm): string
    {
        $this->app['router']
            ->get('probe/'.$realm, \Schemastud\Frame\Http\Controllers\FrameManifestController::class)
            ->defaults('realm', $realm);

        return $this->getJson('probe/'.$realm)->json('nav.items.0.label');
    }
}

/**
 * A realm-discriminating stand-in for whatever a host's real navigation projector is. It records
 * every realm it was asked for so a test can prove the manifest passed the realm through rather
 * than merely producing a plausible tree.
 */
class FixtureNavContributor implements FrameNavContributor
{
    /** @var array<int, string> */
    public array $asked = [];

    /**
     * @param  array<int, string>  $declines
     */
    public function __construct(private array $declines = []) {}

    public function contributeNav(?string $realm): ?array
    {
        $this->asked[] = (string) $realm;

        if (in_array((string) $realm, $this->declines, true)) {
            return null;
        }

        return [
            'nav' => ['items' => [['label' => 'nav-for-'.$realm]]],
            'routeContext' => [new RouteContextEntry(routeName: 'leaf', path: $realm.'-leaf')],
        ];
    }
}
