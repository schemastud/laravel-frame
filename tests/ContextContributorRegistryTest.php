<?php

namespace Schemastud\Frame\Tests;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Rushing\Popcorn\Registries\RegistryIndex;
use Schemastud\Frame\Contracts\ResourceContextContributor;
use Schemastud\Frame\Registry\ContextContributorRegistry;
use Schemastud\Frame\Registry\ContextManifest;
use Schemastud\Frame\Tests\Fixtures\ContactResourceData;

/**
 * frame-foundation 93: the `frame.context-contributors` registry, and decision 87's merge-order table pinned row by row.
 *
 * Fixture: `ContactResourceData` under the key `contacts`. Its reflected declaration (R) has `edit` (widget
 * `email-input`) and `row-cell` at pointer `email`, and nothing for `detail` there. A, B, H are fixture contributors
 * registered in that order; `c` is `edit`, `d` is `detail`, `p` is `email` unless a row says otherwise.
 */
class ContextContributorRegistryTest extends TestCase
{
    private const KEY = 'contacts';

    // ---- the registry itself ------------------------------------------------------------------------------------

    public function test_it_is_declared_at_its_root_with_two_rungs_and_is_in_the_popcorn_index(): void
    {
        $registry = $this->app->make(ContextContributorRegistry::class);

        $this->assertSame(['declared', 'contributed'], $registry->rungs());
        $this->assertSame($registry, $this->app->make(ContextContributorRegistry::class), 'one per container');
        $this->assertContains('frame.context-contributors', array_map(strval(...), app(RegistryIndex::class)->keys()));

        $exit = Artisan::call('popcorn:registries', ['--json' => true]);
        $row = collect(json_decode(Artisan::output(), true))->firstWhere('root', 'frame.context-contributors');
        $this->assertSame(0, $exit, Artisan::output());
        $this->assertNotNull($row, Artisan::output());
        $this->assertSame(Closure::class, $row['entryType']);
    }

    public function test_an_entry_must_be_a_closure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ContextContributorRegistry)->register('acme.nodes', $this->contributor([]));
    }

    public function test_a_factory_that_returns_no_contributor_is_refused_on_read(): void
    {
        $registry = (new ContextContributorRegistry)->register('acme.nodes', static fn () => 'not a contributor', by: 'acme');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('frame.context-contributors.acme.nodes');
        $registry->nodesFor(self::KEY);
    }

    public function test_popcorn_keys_lists_the_contributors_in_answer_order_with_registrants_and_the_supersessions(): void
    {
        $registry = $this->app->make(ContextContributorRegistry::class);
        $registry->register('acme.first', $this->factory([]), by: 'acme/first');
        $registry->register('acme.second', $this->factory([]), by: 'acme/second');
        $registry->register('acme.first', $this->factory([]), by: 'host/app');

        Artisan::call('popcorn:keys', ['prefix' => 'frame.context-contributors', '--json' => true]);
        $live = json_decode(Artisan::output(), true);
        $this->assertSame(['frame.context-contributors.acme.first', 'frame.context-contributors.acme.second'], $live['keys']);
        $this->assertSame(['host/app', 'acme/second'], array_values($live['registrants']));

        Artisan::call('popcorn:keys', ['prefix' => 'frame.context-contributors', '--supersessions' => true, '--json' => true]);
        $replaced = json_decode(Artisan::output(), true)['supersessions'];
        $this->assertCount(1, $replaced);
        $this->assertSame('frame.context-contributors.acme.first', $replaced[0]['key']);
        $this->assertSame('acme/first', $replaced[0]['by']);
    }

    // ---- decision 87's merge-order table, rows 1 to 14 (13 is beam-exec's estate test) ----------------------------

    public function test_row_01_no_registry_passed_is_reflection_only(): void
    {
        $this->assertSame($this->reflected(), (new ContextManifest)->forResource(ContactResourceData::class, null, self::KEY));
    }

    public function test_row_02_a_registry_with_nothing_registered_is_identical_to_row_1(): void
    {
        $this->assertSame($this->reflected(), (new ContextManifest(new ContextContributorRegistry))->forResource(ContactResourceData::class, null, self::KEY));
    }

    public function test_row_03_a_null_key_calls_no_factory(): void
    {
        $called = 0;
        $registry = (new ContextContributorRegistry)->register('acme.nodes', static function () use (&$called): ResourceContextContributor {
            $called++;

            throw new \LogicException('no factory may run for a null key');
        }, by: 'acme');

        $this->assertSame($this->reflected(), (new ContextManifest($registry))->forResource(ContactResourceData::class));
        $this->assertSame(0, $called);
    }

    public function test_row_04_reflection_wins_a_context_a_contributor_also_names(): void
    {
        $email = $this->byNode($this->registry(['acme.a' => ['email' => ['edit' => ['widget' => 'A']]]]))['email'];

        $this->assertSame('email-input', $email['edit']['widget']);
    }

    public function test_row_05_contributed_keys_come_first_then_reflection_only_keys(): void
    {
        $email = $this->byNode($this->registry(['acme.a' => ['email' => ['edit' => ['widget' => 'A'], 'detail' => ['widget' => 'A-detail']]]]))['email'];
        $reflected = $this->reflected()['byNode']['email'];

        $this->assertSame($reflected['edit'], $email['edit'], "c is R's");
        $this->assertSame(['widget' => 'A-detail'], $email['detail'], "d is A's");
        $this->assertSame(['edit', 'detail', ...array_values(array_diff(array_keys($reflected), ['edit', 'detail']))], array_keys($email));
    }

    public function test_row_06_a_later_contributor_wins_a_shared_context_whole(): void
    {
        $p = $this->byNode($this->registry([
            'acme.a' => ['commerce.plan' => ['list-column' => ['label' => 'A', 'sort' => 1]]],
            'acme.b' => ['commerce.plan' => ['list-column' => ['label' => 'B']]],
        ]))['commerce.plan'];

        $this->assertSame(['list-column' => ['label' => 'B']], $p, "B's entry, whole: no deep merge");
    }

    public function test_row_07_two_contributors_naming_different_contexts_both_land(): void
    {
        $p = $this->byNode($this->registry([
            'acme.a' => ['commerce.plan' => ['list-column' => ['label' => 'A']]],
            'acme.b' => ['commerce.plan' => ['detail' => ['label' => 'B']]],
        ]))['commerce.plan'];

        $this->assertSame(['list-column' => ['label' => 'A'], 'detail' => ['label' => 'B']], $p);
    }

    public function test_row_08_pointer_order_is_reflected_then_contributor_only_in_first_appearance(): void
    {
        $byNode = $this->byNode($this->registry([
            'acme.a' => ['z.a' => ['x' => []], 'email' => ['detail' => []]],
            'acme.b' => ['m.b' => ['x' => []], 'z.a' => ['y' => []]],
        ]));
        $reflectedPointers = array_keys($this->reflected()['byNode']);

        $this->assertSame([...$reflectedPointers, 'z.a', 'm.b'], array_keys($byNode));
        $this->assertSame('', $reflectedPointers[0], 'the root pointer first');
    }

    public function test_row_09_a_superseded_contributor_answers_from_its_slot(): void
    {
        $registry = $this->registry([
            'acme.a' => ['commerce.plan' => ['list-column' => ['label' => 'A']]],
            'acme.b' => ['commerce.plan' => ['list-column' => ['label' => 'B']]],
        ]);
        $registry->register('acme.a', $this->factory(['commerce.plan' => ['list-column' => ['label' => "A'"]]]), by: 'host/app');

        $this->assertSame(['label' => 'B'], $this->byNode($registry)['commerce.plan']['list-column'], "B still wins: A' sits in A's slot");
        $this->assertSame('host/app', $registry->registrantOf('acme.a'));
    }

    public function test_row_10_a_host_key_registered_last_wins(): void
    {
        $registry = $this->registry([
            'acme.a' => ['email' => ['detail' => ['label' => 'A']]],
            'acme.b' => ['email' => ['detail' => ['label' => 'B']]],
            'host.h' => ['email' => ['detail' => ['label' => 'H']]],
        ]);

        $this->assertSame(['label' => 'H'], $this->byNode($registry)['email']['detail']);
    }

    public function test_row_11_reflection_still_wins_over_the_host_key(): void
    {
        $registry = $this->registry([
            'acme.a' => ['email' => ['edit' => ['widget' => 'A']]],
            'acme.b' => ['email' => ['edit' => ['widget' => 'B']]],
            'host.h' => ['email' => ['edit' => ['widget' => 'H']]],
        ]);

        $this->assertSame('email-input', $this->byNode($registry)['email']['edit']['widget']);
    }

    public function test_row_12_a_scoped_contributor_resolved_per_call_carries_the_second_requests_nodes(): void
    {
        // A's contributor is its package's own scoped() binding that reads the request; its factory resolves it per
        // call through the CURRENT container and captures nothing at boot. (The Octane sandbox itself is not proven here.)
        $this->app->scoped(RequestReadingContributor::class, static fn ($app): RequestReadingContributor => new RequestReadingContributor($app->make('request')));
        $registry = $this->app->make(ContextContributorRegistry::class);
        $registry->register('acme.a', static fn (): ResourceContextContributor => app(RequestReadingContributor::class), by: 'acme');
        $manifest = $this->app->make(ContextManifest::class);

        $this->app->instance('request', Request::create('/?label=first'));
        $first = $manifest->forResource(ContactResourceData::class, null, self::KEY)['byNode']['commerce.plan'];

        $this->app->forgetScopedInstances();
        $this->app->instance('request', Request::create('/?label=second'));
        $second = $manifest->forResource(ContactResourceData::class, null, self::KEY)['byNode']['commerce.plan'];

        $this->assertSame('first', $first['list-column']['label']);
        $this->assertSame('second', $second['list-column']['label']);
    }

    public function test_row_14_a_factory_answering_nothing_leaves_the_fold_unchanged(): void
    {
        $with = $this->registry([
            'acme.a' => ['commerce.plan' => ['list-column' => ['label' => 'A']]],
            'acme.empty' => [],
            'acme.b' => ['email' => ['detail' => ['label' => 'B']]],
        ]);
        $without = $this->registry([
            'acme.a' => ['commerce.plan' => ['list-column' => ['label' => 'A']]],
            'acme.b' => ['email' => ['detail' => ['label' => 'B']]],
        ]);

        $this->assertSame($this->byNode($without), $this->byNode($with));
    }

    // ---- helpers ------------------------------------------------------------------------------------------------

    /** @return array<string, mixed> the reflection-only block */
    private function reflected(): array
    {
        return (new ContextManifest)->forResource(ContactResourceData::class);
    }

    /** @return array<string, array<string, mixed>> */
    private function byNode(ContextContributorRegistry $registry): array
    {
        return (new ContextManifest($registry))->forResource(ContactResourceData::class, null, self::KEY)['byNode'];
    }

    /** @param  array<string, array<string, array<string, mixed>>>  $byKey  registry key => the contributor's nodes for `contacts` */
    private function registry(array $byKey): ContextContributorRegistry
    {
        $registry = new ContextContributorRegistry;
        foreach ($byKey as $at => $nodes) {
            $registry->register($at, $this->factory($nodes), by: 'test/'.$at);
        }

        return $registry;
    }

    private function factory(array $nodes): Closure
    {
        $contributor = $this->contributor($nodes);

        return static fn (): ResourceContextContributor => $contributor;
    }

    /** @param  array<string, array<string, mixed>>  $nodes  the nodes this contributor answers for `contacts` */
    private function contributor(array $nodes): ResourceContextContributor
    {
        return new class($nodes) implements ResourceContextContributor
        {
            public function __construct(private array $nodes) {}

            public function nodesFor(string $key): array
            {
                return $key === 'contacts' ? $this->nodes : [];
            }
        };
    }
}

/** Row 12's request-reading contributor: a package's own scoped() binding. */
final class RequestReadingContributor implements ResourceContextContributor
{
    public function __construct(private Request $request) {}

    public function nodesFor(string $key): array
    {
        return ['commerce.plan' => ['list-column' => ['label' => (string) $this->request->query('label')]]];
    }
}
