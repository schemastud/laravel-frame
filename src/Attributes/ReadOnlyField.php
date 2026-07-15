<?php

namespace Schemastud\Frame\Attributes;

use Attribute;
use Schemastud\Frame\Strategies\ReadOnlyAttributeStrategy;

/**
 * Marks an edit-DTO property present-but-disabled in the form — projected to the
 * standard JSON-Schema `readOnly: true` by {@see ReadOnlyAttributeStrategy}.
 *
 * Distinct from spatie `#[Computed]` (which is dropped from the request schema
 * entirely): a #[ReadOnlyField] property is display context a human should SEE but
 * not EDIT — it stays in the form, disabled. Named `ReadOnlyField` because
 * `ReadOnly` collides with PHP's reserved `readonly` keyword.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ReadOnlyField {}
