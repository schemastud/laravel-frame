<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model-backed resource with NO policy bound — the arm the write gate fails CLOSED on, and the
 * one that differs from the read axis (nav keeps such a resource visible; a write is refused).
 */
class UnpolicedModel extends Model
{
    protected $guarded = [];

    protected $table = 'sample_models';
}
