<?php

namespace Schemastud\Frame\Registry;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ResourceLayout: string
{
    case Single = 'single';
    case Subnav = 'subnav';
    case MasterDetail = 'master-detail';
}
