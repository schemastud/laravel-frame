<?php

namespace Schemastud\Frame\Tests;

use PHPUnit\Framework\TestCase;
use Rushing\Popcorn\Registries\Docs\RegistryDocsCheck;

/**
 * This package adopts the package-tier registry docs check over the whole of `src/`: its one declared
 * root, `frame.resources`, has its `docs/registries/frame.resources.mdx` page, and the page's generated
 * facts block is byte-identical to a fresh render of the `#[IsRegistry]` declaration.
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
        $this->assertSame(['frame.resources'], array_keys($report->coverage));
    }
}
