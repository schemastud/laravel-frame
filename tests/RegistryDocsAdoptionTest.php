<?php

namespace Schemastud\Frame\Tests;

use PHPUnit\Framework\TestCase;
use Rushing\Popcorn\Registries\Docs\RegistryDocsCheck;

/**
 * This package adopts the package-tier registry docs check over the whole of `src/`: each declared root,
 * `frame.resources` and (frame-foundation 90) `frame.contexts`, has its `docs/registries/<root>.mdx` page,
 * and the page's generated facts block is byte-identical to a fresh render of the `#[IsRegistry]` declaration.
 *
 * The per-producer `frame.resources.{member}` declarations are built at runtime, not by attribute, so
 * they are members of that root rather than roots of their own and the scan does not count them.
 * The coverage count is asserted, so an inactive check cannot pass by reporting no failures.
 */
class RegistryDocsAdoptionTest extends TestCase
{
    public function test_every_declared_root_is_documented_with_its_facts_in_step(): void
    {
        $report = RegistryDocsCheck::forPackage(dirname(__DIR__))->run();

        $this->assertSame([], $report->failures(), $report->summary());
        $coverage = array_keys($report->coverage);
        sort($coverage);
        $this->assertSame(['frame.context-contributors', 'frame.contexts', 'frame.resources'], $coverage);
    }
}
