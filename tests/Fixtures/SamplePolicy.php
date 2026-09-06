<?php

namespace Schemastud\Frame\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * A policy shaped like the real ones the socket meets — `rushing/laravel-permission-cascade`'s
 * `BaseModelPolicy` and any hand-written Laravel policy: class-level `create($user)`, instance-level
 * `update($user, $instance)` / `delete($user, $instance)`, every first argument typed
 * `Authenticatable` so a guest auto-denies without this class branching on it.
 *
 * Answers are driven by a static allow-list of ability names so one fixture serves the
 * member-denied and owner-allowed halves of every test without a permissions table.
 *
 * ⚠️ It deliberately does NOT define `viewAny`. The read axis must stay open for a resource whose
 * index leans on its row scope, and a policy that answered every ability would hide a regression
 * where the write gate leaked sideways into reads.
 */
class SamplePolicy
{
    /** @var list<string> */
    public static array $allows = [];

    public static function reset(): void
    {
        self::$allows = [];
    }

    public function create(Authenticatable $user): bool
    {
        return in_array('create', self::$allows, true);
    }

    public function update(Authenticatable $user, Model $instance): bool
    {
        return in_array('update', self::$allows, true);
    }

    public function delete(Authenticatable $user, Model $instance): bool
    {
        return in_array('delete', self::$allows, true);
    }
}
