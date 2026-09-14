# Resource filter capabilities

Frame resolves filter behavior from `ResourceDefinition::filterProvider`, a server-side class implementing `ResourceFilterProvider`. The class is hidden from the manifest wire and generated TypeScript. Definition overrides preserve it.

The default resource routes expose schema, options, variants, and variant schema below `resources/{resource}/filters`. A host mounting its own resource exposure calls `Routing\ResourceRoutes::filters()` inside its route group, supplying its path, names, and route defaults. Frame resolves the registered resource and applies `ResourceAccessGate` before it resolves the provider. The provider applies any additional domain authorization.

`FilterSchemaResponseData` wraps the schema document in `data` and advertises an optional `savedViewsResource`. That reference names another registered resource whose ordinary CRUD persists views. The shared `Filters\ResourceFilters` dispatcher resolves its definition and checks resource access before emitting the reference; it never resolves the dependency's filter provider. Unavailable persistence leaves the filter vocabulary intact. `savedViewsCan` carries the referenced resource's current actor permissions, derived from `ResourceAuthorizer`, rather than trusting permissions supplied by a filter provider. Frame supplies no transient persistence fallback. A resource without a provider returns an empty object-shaped vocabulary and no saved-view reference; unsupported options or variant schema requests return 404.

Providers return declared Data envelopes for schema, options, and variants. Option sources receive the resource, reference, and search string; implementations must refuse a reference that the resource's vocabulary does not expose. A globally registered source is not authorization to enumerate it through every resource.

The frontend `FrameTransport` consumes these capabilities. Saved-view support is independent of a nonempty vocabulary: a resource can filter without offering persistence. Saved-view list operations must consume ordinary resource pagination completely.

Providers that validate saved queries implement the optional `ResourceFilterValidator` interface on the same provider. Its `validate()` method returns parameters that can be persisted and applied, or throws a validation error. The persistence resource owns storage and uses that declared validator; Frame does not require a second query-vocabulary registry.

`ResourceAuthorizer::recordCapabilities()` projects policy answers for an already-resolved model, without looking it up again. A policy's 403/404 refusal becomes a false advisory answer so a readable shared record can appear without a mutation control. Endpoint authorization retains its original denial behavior. The frontend requires affirmative server permissions; an injected host permission check can narrow them further.

Variant selection travels as `filterVariant` in the resource query and saved query parameters. `ResourceQueryData` declares its syntax; providers and resource query handlers enforce membership and execution semantics. Selecting a variant must affect the actual query and saved-query validation, not just the schema response. Retained HTTP mounts for framed resources use the same dispatcher as canonical routes so custom providers govern both.

## Migrating the flat filter surface

The former `filter-schema/{resource}`, `filter-options/{ref}`, and `saved-filters` routes no longer mount. Move provider selection from a global binding to the resource declaration and migrate transport calls to the resource root. Persistence belongs to a declared resource and its handler; unbound storage must not report a successful save.

Beam implements the port from its resource declarations and supplies a saved-filter resource. Frame-only applications can provide their own implementations without importing Beam.
