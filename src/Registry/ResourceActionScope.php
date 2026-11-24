<?php

namespace Schemastud\Frame\Registry;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ResourceActionScope: string
{
    case Resource = 'resource';
    case Record = 'record';
}
