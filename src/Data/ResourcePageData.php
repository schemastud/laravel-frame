<?php

namespace Schemastud\Frame\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\Optional as TypeScriptOptional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Spatie\TypeScriptTransformer\Attributes\TypeScriptType;

/** A resource list page; cursor results deliberately omit offset metadata. */
#[TypeScript]
class ResourcePageData extends Data
{
    public function __construct(
        /** @var list<array<string, mixed>> */
        public array $data,
        public int $perPage,
        #[TypeScriptOptional, TypeScriptType('int')]
        public int|Optional $total = new Optional,
        #[TypeScriptOptional, TypeScriptType('int')]
        public int|Optional $page = new Optional,
        #[TypeScriptOptional, TypeScriptType('string|null')]
        public string|Optional|null $nextCursor = new Optional,
    ) {}

    /** @param list<array<string, mixed>> $rows */
    public static function offset(array $rows, int $total, int $page, int $perPage): self
    {
        return new self(array_values($rows), $perPage, $total, $page);
    }

    /** @param list<array<string, mixed>> $rows */
    public static function cursor(array $rows, int $perPage, ?string $nextCursor): self
    {
        return new self(array_values($rows), $perPage, nextCursor: $nextCursor);
    }
}
