# Write forms and read schemas

A resource already declares its read Data class as `ResourceDefinition::data` and its optional
write/edit shape as `editData`. The schema endpoint reflects those declarations; it never infers
a schema from a record or accepts a caller-supplied class name.

| Request | Declared class | Configured generator mode |
| --- | --- | --- |
| `GET /frame/resources/{resource}/schema` | `editData ?? data` | `forRequest()` |
| Same URL with `purpose=write` | `editData ?? data` | `forRequest()` |
| Same URL with `purpose=read` | `data` | `forResponse()` |

The route prefix is host-configured. Existing `frame.resources.schema` and controller `schema`
identities are preserved. `ResourceSchemaQueryData` declares and validates the optional purpose;
write is the default. Invalid purpose, including an array, returns422 **after** resolving the
registered resource and resource reach. Unknown resource remains404, denied reach403; a host's
authentication middleware can reject earlier. The selector grants no record or write permission.
Action-input schemas retain their existing request-mode behavior.

The configured `Generator` chain chooses the appropriate generator. Request mode uses input wire
names and request default/optional rules, and omits computed properties. Response mode uses output
wire names and includes declared output/computed properties; hidden schema properties stay absent.
Read the owning `schemastud/laravel-data-schemas` generator and Spatie Data mapping/validation docs
for their exact rules. Stored/collapsed schemas and version fingerprints are a separate axis.
The read schema does not replace input validation, alter the Data class, or make fields writable.

## Producer and resolver responsibility

`ResourceSchemaResolver::schema(ResourceDefinition, Request)` remains the optional override.
The request has passed purpose validation; read it with `$request->query('purpose', 'write')`.
A returned schema wins; null falls through to the configured chain above. Keep any existing
collection/address query semantics. A long-lived resolver must not cache one tenant/address's
schema under a resource name shared by others.

The default read schema describes `data`. A producer whose record endpoint returns a narrower
public/actor-specific DTO or a custom projection must supply its matching authorized schema through
this resolver. Resource reach alone is not a universal field-level/public projection policy.
Do not advertise the normal read DTO as an anonymous-safe schema. Frame does not know a producer's
public DTO or authorization model and does not invent one.

The record endpoint and schema must describe the same wire properties. A producer may return an
edit DTO from its legacy editable-record endpoint; opting that screen into a normal read schema
without changing its record projection can show missing/misnamed fields. This schema selector does
not add a universal record-projection selector or change the `FrameResourceHandler::show` contract.

## Frontend contract

`@schemastud/frame` accepts an optional third `getFormSchema(resource, form, purpose)` argument.
Its `EditShell` takes explicit `schemaPurpose="read"` for a matching read-only detail producer.
Default purpose stays write, including disabled legacy editors; create always uses write.
`readOnly` controls interaction and Save availability, while `bare/enriched` controls host widget
presentation. Widget render contexts such as edit/detail are another independent metadata axis.

Read/write schema cache identities include purpose, form and transport authority. An old
implementation accepting only two arguments remains compatible for default write. Extra arguments
being ignored does not establish read support: pair a purpose-aware producer and adapter.

The manifest still carries generated dot-form Data type names, never PHP class names. It does not
need another readData field. Types are compile-time contracts, not runtime payload validation.
Remote UI components use the host's published schema/transport contract; this endpoint adds no
remote loader, sandbox, capability delegation or version negotiation.

Owning references: [ADR0001](adr/0001-a-field-on-frames-contract-must-be-read-by-frame.md),
[ADR0004](adr/0004-the-manifest-carries-no-php-class-string.md), and the JS Frame schema-purpose
reference and standalone example. `ResourceSchemaEndpointTest` covers defaults, both modes,
configured dispatch, override/null/address handling, hidden/computed/mapped fields and reach order.
