<?php

namespace Schemastud\Frame\Tests;

use PHPUnit\Framework\TestCase;
use Schemastud\Frame\Registry\GeneratedTypeName;
use Schemastud\Frame\Tests\Fixtures\SampleResourceData;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;

/**
 * The one transform ADR-0002 adds to the manifest wire: a Data class-string becomes the generated
 * type's dot-form name, and nothing else is touched. Pure — no container, no Data object.
 */
class GeneratedTypeNameTest extends TestCase
{
    private function transform(mixed $value): mixed
    {
        return (new GeneratedTypeName)->transform(
            $this->createStub(DataProperty::class),
            $value,
            $this->createStub(TransformationContext::class),
        );
    }

    public function test_a_class_string_becomes_its_generated_dot_form_name(): void
    {
        $this->assertSame('Schemastud.Frame.Tests.Fixtures.SampleResourceData', $this->transform(SampleResourceData::class));
    }

    public function test_a_leading_backslash_does_not_produce_a_leading_dot(): void
    {
        $this->assertSame('App.Data.RowData', $this->transform('\\App\\Data\\RowData'));
    }

    public function test_a_value_already_in_dot_form_passes_through(): void
    {
        $this->assertSame('App.Data.RowData', $this->transform('App.Data.RowData'));
    }

    public function test_a_non_string_passes_through_untouched(): void
    {
        $this->assertNull($this->transform(null));
    }
}
