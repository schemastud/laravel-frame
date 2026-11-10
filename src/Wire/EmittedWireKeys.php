<?php

namespace Schemastud\Frame\Wire;

use Schemastud\Frame\Vocabulary\WidgetVocabulary;

/**
 * Every key laravel-frame emits on the wire, with its reader (frame-foundation ticket 05, ruling 6; ticket 23).
 *
 * A key added to the manifest, the context block or the schema keywords fails `EmittedWireKeysTest` until it is
 * listed here with the code that reads it (CONFORM 05-11). Each key names one of three readers:
 *
 *  - `client`: a host's JS build reads it. With no `by`, `@schemastud/frame` reads it. With `by`, the reader is named
 *    as `<package> <src path>`: host chrome such as `@splicewire/beam-inertia`, or `@schemastud/seam`. `by` is
 *    provenance only. Every `client` key is in {@see self::clientKeys()} and is compared, whether or not it has `by`.
 *  - `server`: a named PHP reader (`by`), such as `x-stud-widget-contexts`, which beam's
 *    `CollectionResourceContextNodes` reads (ticket 01, ruling 6).
 *  - `unread`: no executable code reads it today, with the `reason` it is still emitted.
 *
 * Groups and key spelling:
 *
 *  - `manifest`: the keys of `GET {prefix}/manifest`'s root, and of each `routeContext` entry (`routeContext[].path`).
 *    `nav` is listed and its inside is not: it is the host contributor's own payload.
 *  - `resource-entry`: the keys of one `resources[]` entry, dotted for an object (`nav.label`) and `[]` for a list
 *    (`actions[].key`).
 *  - `context-block`: the keys of one `contexts[<key>]` block, and `entry.<field>` for a context entry inside `byNode`
 *    (`byNode` is pointer => context => entry; both map levels are names, not keys).
 *  - `schema-keywords`: the `x-` keywords on any node of a served schema (write, read, or an action's input).
 *
 * An `options` bag is the author's (emitted verbatim; its keys are the widget's), so its inside is not listed; nor are
 * the map keys of `inherits` (context names) and `can.actions` (action keys).
 *
 * Readers were taken from a census on 2026-11-10 (schemastud JS @ a122330, beam JS @ e16eb73a, tower-ux and the
 * flagship's `ui/src` as checked out). `@schemastud/frame/src` was read without `types.ts`, stories, tests, fixtures
 * and comments. A `by` path names the executable reader. Review checks that a named reader does read its key
 * (ruling 05 `:581-582`).
 */
final class EmittedWireKeys
{
    public const ReaderClient = 'client';

    public const ReaderServer = 'server';

    public const ReaderUnread = 'unread';

    public const GroupManifest = 'manifest';

    public const GroupResourceEntry = 'resource-entry';

    public const GroupContextBlock = 'context-block';

    public const GroupSchemaKeywords = 'schema-keywords';

    /** `@schemastud/frame` reads it. */
    private const Frame = ['reader' => self::ReaderClient];

    private const BeamInertiaRouter = ['reader' => self::ReaderClient, 'by' => '@splicewire/beam-inertia src/frame/router.tsx'];

    private const BeamInertiaManifest = ['reader' => self::ReaderClient, 'by' => '@splicewire/beam-inertia src/frame/manifest.ts'];

    /** Generated type names (ADR-0004) that no JS reads today; kept for a host's typed client. */
    private const TypeNameUnread = [
        'reader' => self::ReaderUnread,
        'reason' => "a generated type's dot-form name (ADR-0004) for a host's typed client; no executable code in @schemastud/frame, beam-inertia, tower-ux or the flagship reads it (census 2026-11-10)",
    ];

    /** A resource-entry key that repeats a context-block key: frame reads the context block's copy. */
    private const ContextBlockCopy = [
        'reader' => self::ReaderUnread,
        'reason' => 'repeated on the resource entry; @schemastud/frame reads the context block\'s copy (contexts[key]), and no chrome reads this one (census 2026-11-10)',
    ];

    /**
     * group => key => reader.
     *
     * @return array<string, array<string, array{reader: string, by?: string, reason?: string}>>
     */
    public static function all(): array
    {
        return [
            self::GroupManifest => [
                'resources' => self::Frame,                       // httpTransport.ts:117
                'contexts' => self::Frame,                        // kit.tsx:153-166
                'nav' => ['reader' => self::ReaderClient, 'by' => '@splicewire/beam-inertia src/components/frame-rail.tsx, src/frame/router.tsx'],
                'routeContext' => self::BeamInertiaRouter,        // router.tsx:170-248
                'routeContext[].routeName' => self::Frame,        // routes.ts
                'routeContext[].path' => self::Frame,             // routes.ts
                'routeContext[].shell' => self::Frame,            // routes.ts:292-311
                'routeContext[].lazy' => [
                    'reader' => self::ReaderUnread,
                    'reason' => "the host's lazy()/Suspense hint (RouteContextEntry); declared in frame's types.ts, read by no executable code in frame, beam-inertia, tower-ux or the flagship (census 2026-11-10)",
                ],
                'routeContext[].guard' => self::Frame,            // routes.ts:272-280
                'routeContext[].mounts' => self::Frame,           // mountDispatcher.tsx:158-190
                'routeContext[].widget' => self::Frame,           // mountDispatcher.tsx
                'routeContext[].resource' => self::Frame,         // mountDispatcher.tsx:158
            ],
            self::GroupResourceEntry => [
                'key' => self::BeamInertiaManifest,               // manifest.ts:120 (resources.find by key)
                'data' => self::TypeNameUnread,
                'creatable' => ['reader' => self::ReaderClient, 'by' => 'splicewire/splicewire-app ui/src/frame/abilities.ts'],
                'editData' => self::TypeNameUnread,
                'nav' => ['reader' => self::ReaderClient, 'by' => '@splicewire/tower-ux src/customer/operator.tsx'],
                'nav.label' => ['reader' => self::ReaderClient, 'by' => '@splicewire/tower-ux src/customer/operator.tsx'],
                'nav.group' => self::navUnread('group'),
                'nav.icon' => self::navUnread('icon'),
                'nav.section' => self::navUnread('section'),
                'nav.navOrder' => self::navUnread('navOrder'),
                'nav.routeName' => self::navUnread('routeName'),
                'form' => self::BeamInertiaManifest,              // manifest.ts:120 (formFor)
                'layout' => self::ContextBlockCopy,
                'deletable' => [
                    'reader' => self::ReaderUnread,
                    'reason' => 'the resource-level delete gate; clients read the per-actor contexts[key].can.delete instead, and no executable code reads this key (census 2026-11-10)',
                ],
                'editable' => self::BeamInertiaRouter,            // router.tsx:239
                'showable' => self::BeamInertiaRouter,            // router.tsx:239
                'createAffordance' => self::ContextBlockCopy,
                'singularLabel' => self::ContextBlockCopy,
                'createResultData' => self::TypeNameUnread,
                'actions' => self::ContextBlockCopy,
                'actions[].key' => self::ContextBlockCopy,
                'actions[].label' => self::ContextBlockCopy,
                'actions[].scope' => self::ContextBlockCopy,
                'actions[].method' => self::ContextBlockCopy,
                'actions[].url' => self::ContextBlockCopy,
                'actions[].input' => self::ContextBlockCopy,
                'actions[].result' => self::ContextBlockCopy,
                'actions[].destructive' => self::ContextBlockCopy,
                'collections' => self::ContextBlockCopy,
                'collections[].stem' => self::ContextBlockCopy,
                'collections[].label' => self::ContextBlockCopy,
            ],
            self::GroupContextBlock => [
                'byNode' => self::Frame,                          // resolveWidgetFor.ts, rowActions.ts, RecordPicker.tsx
                'inherits' => self::frameConstant('inherits', 'INHERITS'),
                'known' => self::frameConstant('known', 'its context list'),
                'layout' => [
                    'reader' => self::ReaderUnread,
                    'reason' => "the FrameLayout socket's variant token (ticket 31); declared in frame's contexts.ts, read by no executable code in frame or the chrome (census 2026-11-10)",
                ],
                'createAffordance' => self::Frame,                // ListShell.tsx:95
                'singularLabel' => self::Frame,                   // ListShell.tsx, RecordPicker.tsx:128
                'can' => self::Frame,                             // kit.tsx:158
                'can.create' => self::Frame,                      // kit.tsx:158 (can?.[action])
                'can.update' => self::Frame,                      // kit.tsx:158 (can?.[action])
                'can.delete' => self::Frame,                      // kit.tsx:158 (can?.[action])
                'can.actions' => self::Frame,                     // actions.ts
                'actions' => self::Frame,                         // actions.ts, ResourceActions.tsx
                'actions[].key' => self::Frame,
                'actions[].label' => self::Frame,
                'actions[].scope' => self::Frame,
                'actions[].method' => self::Frame,                // resourceTransport.ts
                'actions[].url' => self::Frame,                   // actions.ts
                'actions[].input' => self::Frame,                 // actions.ts, ResourceActions.tsx
                'actions[].result' => self::Frame,                // ResourceActions.tsx
                'actions[].destructive' => self::Frame,           // slots/defaults.tsx, RecordPicker.tsx
                'collections' => self::Frame,                     // RecordPicker.tsx, ListShell.tsx
                'collections[].stem' => self::Frame,
                'collections[].label' => self::Frame,
                'entry.participates' => self::Frame,              // resolveWidgetFor.ts
                'entry.widget' => self::Frame,                    // resolveWidgetFor.ts
                'entry.options' => self::Frame,                   // resolveWidgetFor.ts
                'entry.sort' => self::Frame,                      // resolveColumns.ts:69
                'entry.label' => self::Frame,                     // resolveColumns.ts:84
                'entry.inheritsBinding' => self::Frame,           // resolveWidgetFor.ts:69
                'entry.heavyweight' => self::Frame,               // SchemaView.tsx, FiveRegionEditShell.tsx
            ],
            self::GroupSchemaKeywords => [
                'x-widget' => self::Frame,                        // resolveWidgetFor.ts; seam ui-schema.ts
                'x-widget-options' => ['reader' => self::ReaderClient, 'by' => '@schemastud/seam src/registry.ts, src/ui-schema.ts'],
                'x-stud-resource-ref' => self::Frame,             // ResourceRefWidget.tsx
                'x-stud-widget-contexts' => ['reader' => self::ReaderServer, 'by' => 'Splicewire\Beam\Collections\CollectionResourceContextNodes'],
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(string $group): array
    {
        return array_keys(self::all()[$group] ?? []);
    }

    /**
     * Every `client` key, with `by` or without, as a sorted `"<group>:<key>"` string. These are the strings the host's
     * `reads` must hold: the union of frame's readers module and each `--readers` module. `frame.wire-keys` reports
     * each one {@see WidgetVocabulary::wireKeys()} lacks.
     *
     * @return list<string>
     */
    public static function clientKeys(): array
    {
        $keys = [];

        foreach (self::all() as $group => $entries) {
            foreach ($entries as $key => $reader) {
                if ($reader['reader'] === self::ReaderClient) {
                    $keys[] = $group.':'.$key;
                }
            }
        }

        sort($keys, SORT_STRING);

        return $keys;
    }

    /** @return array{reader: string, reason: string} */
    private static function navUnread(string $field): array
    {
        return [
            'reader' => self::ReaderUnread,
            'reason' => "the resource's own nav.{$field}; the realm rail is drawn from the manifest root's nav (the host contributor's), and no executable code reads this key (census 2026-11-10)",
        ];
    }

    /** @return array{reader: string, reason: string} */
    private static function frameConstant(string $key, string $constant): array
    {
        return [
            'reader' => self::ReaderUnread,
            'reason' => "frame resolves with {$constant} of its own (resolveWidgetFor.ts:69), not this {$key}; declared in contexts.ts, read by no executable code (census 2026-11-10)",
        ];
    }
}
