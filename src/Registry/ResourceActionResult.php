<?php

namespace Schemastud\Frame\Registry;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ResourceActionResult: string
{
    case Toast = 'toast';
    case Navigate = 'navigate';
}
