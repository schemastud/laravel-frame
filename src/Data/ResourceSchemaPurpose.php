<?php

namespace Schemastud\Frame\Data;

enum ResourceSchemaPurpose: string
{
    case Write = 'write';
    case Read = 'read';
}
