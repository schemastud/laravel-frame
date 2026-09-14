<?php

namespace Schemastud\Frame\Authorization;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use Schemastud\Frame\Contracts\ResourceAccessGate;
use Schemastud\Frame\Registry\ResourceDefinition;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * The WRITE-axis ACTOR gate for Frame's generic resource socket — the one authority both the
 * endpoint and the affordance ask, so a button and the endpoint behind it cannot disagree.
 *
 * ## Why this is in frame, and not in the handler or a host mount
 *
 * Frame's socket ({@see \Schemastud\Frame\Http\Controllers\FrameResourceController}) shipped with
 * **no authorization on the write axis at all**: measured 2026-09-05 at `~/Herd/beam`, a member who
 * holds only `beam-ux-entry.view` could `POST /frame/resources/beam-ux-entry` and got a **422**, not
 * a 403 — validation ran, so nothing had asked a permission question before it. The READ axis was
 * gated, but only in NAV ({@see \Splicewire\Beam\Ux\Frame\FrameResourcesInvocable::resourceViewable()}),
 * never at the endpoint.
 *
 * Three homes were candidates. The other two lose:
 *
 *  - **The {@see \Schemastud\Frame\Contracts\FrameResourceHandler} port.** It is where the model
 *    instance already is, which is genuinely convenient — but the estate has **six** implementations
 *    (beam's `ParticleFrameResourceHandler`, tower's `ConduitResourceHandler`, numero's, schemastud's
 *    two, plus any host's), the interface cannot compel a check, and an implementor who omits one
 *    ships a silent hole indistinguishable from a deliberate opening. That is authorization by
 *    discipline, which is exactly the shape that produced this defect. A gate that must be
 *    remembered N times is not a gate.
 *  - **A host mount / middleware.** `frame.middleware` is ONE ambient list for the whole route
 *    group; it cannot distinguish `index` from `store`, nor one resource from another, and frame —
 *    not the host — declares these routes. api-surface-coherence
 *    [141](../../../../../../splicewire-ecosystem/.scratch/splicewire/splicewire-app/api-surface-coherence/issues/141-twenty-two-hand-written-mounts-are-the-keystone-the-map-filed-as-fog.md)
 *    ruled SPELL IT OUT for mounts on the reasoning that *"every field the 20 mounts pass is a fact
 *    about an **exposure**, which the route file owns."* An ABILITY is not an exposure: one exposure
 *    serves many actors and answers differently for each, so the fact is about the ACTOR × MODEL
 *    pair, which neither the route file nor the resource declaration knows. Laravel's policy layer
 *    is the thing that owns it, and `Gate` is the vocabulary for asking — which is also why this
 *    needs **no `splicewire/*` dependency**: a policy is Laravel's own noun, and
 *    `rushing/laravel-permission-cascade`'s `BaseModelPolicy` already answers
 *    `create`/`update`/`delete` in exactly it.
 *
 * ## Fail closed on WRITE, and only on write
 *
 * `$definition->model === null` (a service-backed union) or a model with **no policy** denies. That
 * is deliberately the OPPOSITE of the read posture beside it, which keeps a policy-less resource
 * VISIBLE (`resourceViewable()`: *"a resource that leans on its row-level scope instead of a class
 * policy"*). The asymmetry is the rule, not an inconsistency, and beam-ux's own docblock already
 * stated it before anything enforced it: *"A missing policy on a WRITE stays denied — one posture
 * per kind, decided at the declaration, not two per surface."* A read that over-shows is a leak of
 * one list; a write that over-allows is a member deleting every record of every resource.
 *
 * It is also the estate's `AGENTS.md` rule applied where it belongs: *an omission and a decision
 * must not be spelled the same*. Before this class, `policy: null` meant both "this resource is
 * deliberately open" and "nobody thought about it", and beam's {@see \Splicewire\Beam\Write\PolicyWriteGate}
 * resolved that ambiguity toward **permit** (*"No per-resource policy: the surrounding auth/realm
 * middleware already authorized the write"*) — an argument that only holds where the route middleware
 * really is the gate, and at `~/Herd/beam` `frame.middleware` is `['web','auth']`, which authorizes
 * nothing beyond "logged in".
 *
 * Denying a request is not the same as throwing at boot
 * (`docs/agents/traps/audits-and-findings.md:13`): nothing here runs at declaration time, and an
 * unresolvable host fact yields a 403 on one request, never an exception on every request.
 *
 * ## Advisory vs. authoritative
 *
 * {@see allows()} is asked TWICE for different purposes. The endpoint asks it with the record's id
 * and the answer is **authoritative** — it is the thing that returns 403. The manifest asks it with
 * no id, to decide whether an affordance is worth rendering, and that answer is **advisory**: with
 * no instance to police, an instance-dependent policy is probed against a fresh unsaved model, which
 * can only be a class-level approximation. Measured at `~/Herd/beam` 2026-09-05 the approximation is
 * exact for the cascade policy (`delete(new BeamUxEntry)` agrees with `delete($realRow)` for both
 * the member and the owner), but a policy like "editable only while status=draft" would answer more
 * generously at class level than per row. That is the safe direction to be wrong in: the client may
 * offer a button the server then refuses, and the server refusing is the invariant.
 */
class ResourceAuthorizer
{
    /**
     * Frame socket verb => policy ability. The right-hand side is Laravel's own CRUD vocabulary —
     * the same seven names `BaseModelPolicy` answers and `#[UseCascadePolicy]` overrides — so a
     * host that has written ordinary policies is already gated by this with no new declaration.
     */
    public const WriteAbilities = [
        'store' => 'create',
        'update' => 'update',
        'destroy' => 'delete',
    ];

    public function __construct(
        protected Gate $gate,
        protected ResourceAccessGate $access,
    ) {}

    /**
     * The REACH axis — asked FIRST, on every verb including the read ones, and before any handler,
     * any validation and any policy question.
     *
     * This is the second half of the same defect the class docblock opens with. The 2026-09-05 gate
     * closed the write axis and left the read axis explicitly open on the argument that "the read
     * posture is decided elsewhere". At every host measured, *elsewhere* turned out to be the NAV
     * projection alone: measured 2026-09-12 at `https://fresh-tower.test`, `demo-member` — an
     * ordinary tenant user with `os.operate` false — read `/frame/resources/users`,
     * `/frame/resources/teams` and `/frame/resources/tenants` with **200**, receiving every user's
     * email, every team and the tenant roster with its owner email. `config('frame.realms')` had
     * placed `users` and `teams` in the operator realm; that list drove which links were *drawn*
     * and gated nothing.
     *
     * So this is deliberately NOT a symmetric read policy — the asymmetry the class docblock defends
     * still stands, and a resource whose index is gated by its row-level scope keeps being served
     * that way. It is the axis ABOVE both: whether the actor may address this resource on this
     * socket at all. Frame cannot answer it (it has no realms); {@see ResourceAccessGate} is the
     * port the producer answers through.
     */
    public function authorizeAccess(ResourceDefinition $definition): void
    {
        if ($this->access->allowsResource($definition)) {
            return;
        }

        throw new AccessDeniedHttpException(
            "The '{$definition->key}' frame resource is not available to this principal."
        );
    }

    /**
     * Authorize a write, or throw the 403 that the socket used to never raise.
     *
     * @param  string  $ability  a policy ability (`create`/`update`/`delete`), NOT a socket verb
     * @param  string|null  $id  the record being written, for the instance-level abilities
     */
    public function authorize(ResourceDefinition $definition, string $ability, ?string $id = null): void
    {
        if ($this->allows($definition, $ability, $id)) {
            return;
        }

        throw new AccessDeniedHttpException(
            "This action is unauthorized for the '{$definition->key}' frame resource."
        );
    }

    /**
     * May the current actor perform `$ability` on this resource?
     *
     * Every arm that cannot reach an affirmative policy answer returns FALSE — see the class
     * docblock. A null actor falls through to the Gate, whose policy methods type their first
     * argument as `Authenticatable` and therefore auto-deny; that is inherited deny-by-default
     * rather than a branch of our own, and it is the same mechanism
     * {@see \Splicewire\Beam\Write\GateWriteGate} relies on.
     */
    public function allows(ResourceDefinition $definition, string $ability, ?string $id = null): bool
    {
        $modelClass = $definition->model;

        // A service-backed union resource has no Eloquent subject for a policy to be about, so
        // frame cannot police it and does not pretend to. Its handler is the only thing that can,
        // and until one does, its writes are refused. (Measured at `~/Herd/beam` 2026-09-05: the one
        // model-less resource, `members`, declares no write verb at all, so this arm denies nothing
        // that was reachable — it is the default for tomorrow's declaration, not a change to today's.)
        if ($modelClass === null || ! is_a($modelClass, Model::class, true)) {
            return false;
        }

        $policy = $this->gate->getPolicyFor($modelClass);

        // No policy, or a policy silent on this ability. Laravel would deny an ability nobody
        // defined anyway; asking explicitly is what makes the READ side's opposite default
        // (`resourceViewable()` skips and stays visible) a stated decision rather than a
        // side effect of which of two code paths happened to run.
        if ($policy === null || ! method_exists($policy, $ability)) {
            return false;
        }

        return $this->gate->allows($ability, $this->subject($modelClass, $ability, $id));
    }

    /**
     * The class-level capability map emitted onto a resource's {@see \Schemastud\Frame\Registry\ContextManifest}
     * block — the ACTOR axis the client never had.
     *
     * The client's affordances were driven entirely by `creatable`/`deletable`/`editable`, which
     * describe the RESOURCE and say nothing about who is asking; the console therefore rendered a
     * "New entry" button and 13 "Delete entry" buttons for a member holding only `.view`. Those
     * flags stay exactly what they were — "may this be created AT ALL" — and this is the second,
     * orthogonal question. It is deliberately not a second spelling of `creatable`: a resource can
     * be `creatable` and un-creatable BY YOU, and collapsing the two would be how a gate stops
     * meaning what its name says.
     *
     * @return array{create: bool, update: bool, delete: bool}
     */
    public function capabilities(ResourceDefinition $definition): array
    {
        return [
            'create' => $definition->creatable && $this->advisory(fn () => $this->allows($definition, 'create')),
            'update' => $definition->editable && $this->advisory(fn () => $this->allows($definition, 'update')),
            'delete' => $definition->deletable && $this->advisory(fn () => $this->allows($definition, 'delete')),
        ];
    }

    /** @return array{create: bool, update: bool, delete: bool} */
    public function recordCapabilities(ResourceDefinition $definition, Model $record): array
    {
        $model = $definition->model;
        if ($model === null || ! $record instanceof $model || ! $this->access->allowsResource($definition)) {
            return ['create' => false, 'update' => false, 'delete' => false];
        }

        $policy = $this->gate->getPolicyFor($record);
        $allows = fn (string $ability) => $policy !== null && method_exists($policy, $ability)
            && $this->advisory(fn () => $this->gate->allows($ability, $record));

        return [
            'create' => $definition->creatable && $this->advisory(fn () => $this->allows($definition, 'create')),
            'update' => $definition->editable && $allows('update'),
            'delete' => $definition->deletable && $allows('delete'),
        ];
    }

    /** Policies may conceal a foreign row with 404. An advisory probe must not abort its readable list. */
    private function advisory(callable $check): bool
    {
        try {
            return $check();
        } catch (AuthorizationException $exception) {
            if ($exception->status() !== null && ! in_array($exception->status(), [403, 404], true)) {
                throw $exception;
            }

            return false;
        } catch (HttpExceptionInterface $exception) {
            if (! in_array($exception->getStatusCode(), [403, 404], true)) {
                throw $exception;
            }

            return false;
        }
    }

    /**
     * What the policy is asked ABOUT.
     *
     * `create` is class-level, so the class-string is the honest subject and Laravel calls
     * `create($user)` with no instance. `update`/`delete` are instance-level — `BaseModelPolicy`
     * types them `(Authenticatable $user, Model $instance)` and would TypeError on a class-string —
     * so the record is resolved.
     *
     * An id that resolves to nothing falls back to a fresh instance rather than 404ing HERE: the
     * handler's own scoped `findOrFail` owns that answer, and it must keep owning it, or frame's
     * unscoped lookup would start deciding existence for a tenant-scoped resource. The fresh
     * instance is also what the manifest's id-less probe uses.
     *
     * @param  class-string<Model>  $modelClass
     */
    protected function subject(string $modelClass, string $ability, ?string $id): Model|string
    {
        if ($ability === 'create') {
            return $modelClass;
        }

        if ($id !== null) {
            $found = $modelClass::query()->find($id);

            if ($found !== null) {
                return $found;
            }
        }

        return new $modelClass;
    }
}
