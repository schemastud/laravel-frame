<?php

namespace Schemastud\Frame\Registry;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ResourceCreateAffordance: string
{
    case Frame = 'frame';
    case Host = 'host';
}
