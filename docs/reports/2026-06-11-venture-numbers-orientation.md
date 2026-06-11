# Venture numbers section: orientation + feature matrix (2026-06-11)

Step 01 of the venture-numbers track. Report only: no code, no entities, no pages were
created. Scope: can Anokii hold the FNPI revenue model (six lanes, worst/likely/best
scenarios, assumptions, gating facts, roll-up) as a living staff-only section, and what
is the smallest honest build. Both repos read at the same pin: site `composer.lock` and
the framework source tree are both **v0.1.0-alpha.202**, so every framework claim below
reflects what production actually runs.

Method: multi-agent recon (10 subsystem scouts, each adversarially re-verified against
the code; one verifier failed on a transient API error and its scout's load-bearing
claims were re-checked by hand instead; a completeness critic then drove five targeted
follow-ups, including a read-only production account inventory over the established Pi
SSH channel). Evidence is cited as `path:line`. Site paths are repo-relative;
`packages/...` paths are the framework monorepo.

**Every figure quoted from the revenue model in this report is placeholder-grade and
must be labeled as such wherever it ever renders.** Nothing in this section may be
publicly visible.

## What the section must hold (the spec tested against)

Six lanes, each carrying worst/likely/best scenarios, visible assumptions, named gating
facts with placeholder-vs-confirmed status, and a cross-lane roll-up. All figures
placeholder-grade: Technology Yr5 ARR 1.24M / 2.48M / 3.86M; Faraday ~50,000 units,
3 SKUs ($15 utility, $20 phone, key fobs 2 for $10), 3-yr sell-through 2% / 20% / 56%;
Sourcing & Services 2x$5k / 5x$15k / 10x$40k per year; Assessments $15k / $25k / $40k
at 1 / 3 / 5 per year (1 in 3 converts to Technology, never double-counted); Defence $0
committed (DAF EOI submitted 2026-06-11), likely ramp to ~$296k/yr by Yr 5; Pathways
roughly breakeven ($2,500 x 5/yr). Roll-up likely ~$270k Yr 1 to ~$3.5M Yr 5,
Technology ~87% of Yr 5. The xlsx stays the modeling tool; Anokii is the status surface.

## Orientation: how the relevant machinery actually works

**Entity machinery.** Entity types register through `config/entity-types.php`, loaded at
boot by the framework's `AppEntityTypeLoader` (`packages/foundation/src/Kernel/Bootstrap/
AppEntityTypeLoader.php:18-42`). The entity class extends `ContentEntityBase` and carries
`#[ContentEntityType]` / `#[ContentEntityKeys]` attributes, but the attributes alone do
NOT register the type: there is no boot-time attribute scanner, the config entry is
mandatory (verified; one scout's "auto-discovered" framing was wrong and is corrected
here). Schema is derived, not migrated: `db:init --sync-schema` materializes the base
table (system keys, `revision_id`, `published_revision_id`, and a `_data` JSON blob that
carries every non-key field) plus the `<type>_revision` table
(`packages/entity-storage/src/SqlSchemaHandler.php:405-523`). Non-column fields stay
queryable because `findBy` falls back to `json_extract` (`packages/entity-storage/src/
Driver/SqlStorageDriver.php:608-617`); the site already depends on this for page paths
and pillar pids. This recipe is proven six times over (page, identity_pillar, document,
document_note, drive_asset, contact_submission).

**Identity Workspace pattern and the shell.** The Anokii shell (sidebar, topbar, tiles)
is entirely app-owned: a static module registry (`src/Anokii/Modules.php`) drives the nav
and dashboard tiles rendered by `templates/anokii/_shell.html.twig`; tools are server-side
Twig with per-template scoped CSS, vanilla JS, no Node build, no Inertia. The 2026-06-10
shell decision lives framework-side as the `waaseyaa/workspace` package (chat-surface
extraction only, opt-in, post-alpha.202, NOT installed in this site and irrelevant to a
numbers section). The Analytics module (2026-06-11) is the freshest proof that a new
section ships in one pass: module entry, controller, template, routes, tests.

**Role gating.** Three workspace roles (administrator/editor/viewer) with flat permission
strings stamped onto user rows by `WorkspaceAccess::apply()` (`src/Access/
WorkspaceAccess.php:111-123`). The `administrator` role short-circuits every permission
check (`packages/user/src/User.php:145`). Every `/anokii/*` route is registered
`allowAll()` with controller-side session checks; entity writes go through per-type
policies. The decisive fact for this feature: **every existing policy grants view to any
authenticated account**. No permission-gated read exists anywhere in the app yet.

**Audit layer.** `audit_event` is a flat append-only table (de-registered as an entity at
alpha.202), actively enforced by the `AppendOnlyAuditDatabase` decorator, with a writer
that never throws. Caveats that matter here: the entity-lifecycle listener attributes
writes to the entity's own `uid` field (always 0 for workspace types), not the acting
session account, and publish-pointer moves emit no audit row at all. The only writer
recording the true acting account is the app's own `McpInvocationAuditor`. Real
attribution therefore lives in app fields snapshotted per revision (the Pillar
`editor_uid`/`editor_label` pattern), not in the framework.

**MCP wire-up.** As built 2026-06-10 and verified in code: bearer-token agent account
(no password, no roles, fail-closed when unset), hand-built tool catalogue, and
`McpAgentScope` allowlists (`src/Mcp/McpAgentScope.php:29,37`) with global publish-field
denials and human-only revision tools. A new entity type is invisible to the agent until
deliberately added to those allowlists: fail-closed by construction.

## Feature matrix

| Capability | Status | Side | Smallest increment that closes the gap |
|---|---|---|---|
| Structured numeric entities | PARTIAL | both | App: new entity types with integer-cents values; enforce ranges in the app service (framework validation is dormant) |
| Tabular rendering | EXISTS | app | None: hand-written Twig tables are the house idiom; copy ~6 lines of CSS |
| Computed roll-ups | PARTIAL | app | App: a read-model service summing in PHP; no framework aggregation exists |
| Revisioned assumptions | PARTIAL | both | App: register types `revisionable: true`; attribution via app fields per the Pillar pattern |
| Per-entity status | PARTIAL | both | App: status string field + service whitelist + confirmed_by fields; permission-gated flip |
| Per-role access | PARTIAL | both | App: first permission-gated view policy, returning Forbidden (not Neutral) |
| MCP agent access | PARTIAL | both | App: two-constant edit in `McpAgentScope` + per-type field denials |
| Data entry | PARTIAL | app | App: seed command from a checked-in data file + workspace edit forms; xlsx import not worth building |
| Charts | MISSING | none | App, optional: inline SVG or CSS bars in Twig; no chart capability exists anywhere |

No capability requires a framework increment. The CI-gated release-cut cost applies to
none of the recommended path; the few framework-side options named below are optional
hardening only.

### Structured numeric entities: PARTIAL

The framework ships 17 registered field types including integer, float, decimal,
boolean, json, list, and enum (`packages/entity/src/Attribute/FieldTypeInferrer.php:28-46`),
plus a live per-field casting mechanism (`EntityBase::$casts` through `ValueCaster`,
`packages/entity/src/EntityBase.php:149-168`) that enforces int/float/bool/enum types on
get/set with zero extra wiring. But on the default sql-blob backend all non-key values
ride the `_data` JSON blob regardless of declared type, and **declared constraints are
never enforced at save time**: `EntityRepository` validates only when a validator is
injected, and the kernel factory never injects one
(`packages/foundation/src/Kernel/AbstractKernel.php:239-247`). No numeric Range
derivation exists at all. The site idiom is untyped `get()`/`set()` with ad hoc service
validation (`src/Identity/PillarService.php:151`), and the venture build should follow
it: integer cents for currency (the decimal type's declared `decimal(10,2)` silently
degrades to float on one schema path, `packages/entity-storage/src/SqlSchemaHandler.php:1013`),
`$casts` declarations for cheap type safety, range/enum rules in the app service at the
write boundary. `findBy` works on blob fields (equality); range operators exist on the
storage query with numeric coercion for JSON storage.

### Tabular rendering: EXISTS

The canonical example is the Analytics module: controller shapes plain arrays
(`src/Analytics/AnalyticsReport.php:30-110`), template renders hand-written
`<table class="at">` markup with right-aligned `tabular-nums` cells
(`templates/anokii/analytics.html.twig:17-22,51-60`). A 6-lane by 5-year grid with three
scenario columns plus per-lane assumption tables is comfortably within this idiom; Twig
`number_format` covers currency display. There is no shared table component to reuse
(each module carries its own ~6 lines of table CSS), and one adjacent path worth knowing:
the shell's markdown renderer (`public/js/anokii-md.js:87-95`) already turns markdown
pipe tables into styled tables in the chat surfaces, so the agent can present numeric
tables conversationally with zero Twig work.

### Computed roll-ups: PARTIAL (honestly: plain PHP, and that is fine)

The framework has no aggregation surface anywhere: the query builders expose no SUM or
GROUP BY (the only aggregate is `countQuery()`'s hardcoded COUNT), and the `computed`
field type is a vestigial stub whose `compute()` is never called by any pipeline
(`packages/field/src/Item/ComputedItem.php:46-49`). The house pattern, with direct
precedents on both sides, is: load entities via repository, sum in PHP in a read-model
service, render (`src/Controller/ContactInboxController.php:42-63` is the exact shape;
the framework's own ai-observability cost tracker sums rows in PHP too). Raw-SQL
aggregation via `DatabaseInterface` is sanctioned for non-entity tables
(`src/Analytics/AnalyticsReport.php` does GROUP BY today) but is wrong for entities
because values live in the `_data` blob. At six lanes the roll-up is a trivial loop;
numbers stay precomputed in the xlsx for anything heavier, and the section renders the
statused snapshot.

### Revisioned assumptions: PARTIAL

Any type registered `revisionable: true, revisionDefault: true` gets a full field-value
snapshot row per save (`<type>_revision`: entity_id, monotonic revision_id,
revision_created, revision_log, all values), exactly like the four workspace types
today. Two sharp edges, both verified: (1) **the framework never records WHO**: the
`revision_author` column exists only in a dormant schema builder, `RevisionMetadata` is
never constructed in production code, so attribution must be app fields snapshotted per
revision (Pillar's `editor_uid`/`editor_label` plus a `recordEdit()` log line is the
proven pattern; Pages history currently renders no editor and a blank timestamp, so copy
the Identity history endpoint, not the Pages one). (2) Revision history is durable but
not structurally append-only: `withoutNewRevision` rewrites the current revision in
place, `pruneRevisions` deletes old ones, entity delete wipes all of them. For
"placeholder became confirmed fact, with history," per-save snapshots plus an app-written
log line ("Status: placeholder -> confirmed by <label>") deliver the requirement. The
true append-only guarantee lives in `audit_event`; an explicit audit row on each confirm
(the `McpInvocationAuditor` pattern) is the belt-and-braces option.

### Per-entity status: PARTIAL

The working idiom is `identity_pillar`: a plain string status field with an app constant
whitelist, editor stamp on every edit, revision log summary, history panel rendering
editor per revision. Nothing gates transitions; "who confirmed" comes from the app
fields, not the framework. `packages/workflows` is installed but effectively dormant
(its one wired path hard-rejects everything except `node` entities); its
`workflow_audit` array of `{from,to,uid,at}` entries is a design reference, not a
drop-in. The venture build: `status` in `{placeholder, confirmed}` plus
`confirmed_by_uid`/`confirmed_by_label`/`confirmed_at`, with the flip gated behind a
dedicated permission, mirroring the existing `edit pages` vs `publish pages` split.

### Per-role access: PARTIAL, with one rule that must not be violated

The substrate exists (roles, permissions, route guards, `#[PolicyAttribute]` policies,
field policies), but the venture section is the first consumer needing a permission-gated
READ. The mechanism is the entity policy, not a route guard: JSON:API, GraphQL, and
admin-surface routes are auto-registered for every entity type and consult only the
policy, so gating `/anokii/ventures` alone would not contain the data. The one rule:
**the venture policy's view must return Forbidden, not Neutral, for accounts lacking the
staff permission.** Reason: access semantics are asymmetric by layer. Entity checkpoints
deny unless allowed, but the entity query layer drops only Forbidden rows
(`packages/entity-storage/src/SqlEntityQuery.php:402`) and field access is
open-by-default, so Neutral-denial protects the HTTP checkpoints but leaks through any
future query-layer consumer. A new policy lands in two places: the `#[PolicyAttribute]`
kernel discovery and the hand-built `WorkspaceAccess::handler()` list
(`src/Access/WorkspaceAccess.php:130-140`). The framework also ships a complete
clearance-gated read system (`packages/field/src/Classification/`, wildcard policy
returning Forbidden below clearance) that is live-but-inert in this site; it is the
framework-native alternative if FNPI ever wants data classification levels, but a small
hand-written policy is the right first step.

**Leak-surface inventory for a new staff-only entity type** (each verified in code):

| Surface | Exposure for a permission-gated venture type |
|---|---|
| JSON:API `GET /api/{type}` | Auto-registered for every type; policy-gated per row; **the type NAME leaks publicly** via anonymous `GET /api` discovery (`packages/api/src/ApiDiscoveryController.php:30-35`); show answers "forbidden" not 404 (existence oracle) |
| GraphQL `/graphql` | Same profile: allowAll route, per-entity policy check |
| Admin SPA `/admin/_surface/*` | Gated by the `administer content` permission (`packages/admin-surface/src/Host/GenericAdminSurfaceHost.php:49`), and every registered type appears in its catalog to administrators; the policy, not obscurity, is the protection |
| MCP `/mcp` | Fail-closed: invisible until added to `McpAgentScope` allowlists |
| Co-Intelligence chat | Fail-closed: `AgentTools::WORKSPACE_TYPES` allowlist; RAG corpus is bundled docs + published pages + pillars only. Note: extending either is a broadcast to every signed-in workspace user |
| Public site | Renders only published `page` revisions by path; venture entities unreachable |
| Search / sitemap / feeds | Inert: search indexing is opt-in and unimplemented; no sitemap or feed routes exist |
| Bearer JWT | Dormant (`jwt_secret` empty, `api_keys` empty), but pipeline-wide if ever configured; widens "authenticated" to machine principals |

Consequence of the type-name leak: give the entity types neutral ids (`venture_lane`,
`gating_fact`); nothing defence-specific in a type id, ever.

### MCP agent access: PARTIAL

Verified current state: read scope page/identity_pillar/document/document_note/
drive_asset, write scope the four revisionable ones, global denied fields
published_revision_id/revision_id/published/moderation_state, denied tools
set_current_revision/rollback, delete capability never granted. Extending to venture
types is a two-constant edit (`McpAgentScope::READ_TYPES`/`WRITE_TYPES`) plus the
entity-type registrations; the catalogue is type-agnostic. The write-scope types MUST be
registered revisionable or agent writes become live mutations instead of historied
drafts (the constraint is stated in the scope code itself, `src/Mcp/McpAgentScope.php:31-35`).
Three facts to design around, all verified:

1. **Zero value validation runs on the MCP write path.** The endpoint forwards arguments
   unvalidated, tools `set()` every key verbatim, the repository validator is dormant,
   and SQLite affinity rejects nothing. The agent could write a negative revenue or a
   string into a numeric field today; the backstops are draft semantics, audit, and human
   review. Cheapest closure: per-type value checks in `McpAgentScope::guard` (numeric,
   non-negative, status enum) for the venture types.
2. **Per-field denials are the human-only lever**: add `status`, `confirmed_by_*` (and
   any publish-like marker) for venture types to `DENIED_FIELDS_BY_TYPE`, exactly as
   `page.status` is denied today. The global denied list already covers the publish
   pointers for any new type automatically.
3. **Draft invisibility is not automatic.** The `published_revision_id` column exists on
   every revisionable base table and `loadPublishedRevision`/`setPublishedRevision` are
   generic, but only Pages wires them. For non-page types an MCP write becomes the
   current, visible revision immediately (historied, but live in any staff view reading
   the draft head). If the venture screens must distinguish "agent-drafted" from
   "human-published" numbers, the app must wire the pointer (the Pages pattern) or a
   status convention; this is part of the build, not free.

Concurrency, from a targeted follow-up: there is no optimistic locking anywhere in the
save path; concurrent writes are silent last-write-wins, recoverable only from revision
history. Disjoint-field edits merge cleanly (both write paths set only supplied fields),
so per-lane entities keep collisions rare. The in-app proposal flow
(`src/CoIntelligence/AgentConversation.php`) gives field-level diffs and attribution but
re-executes against the then-current head at apply time with no staleness re-check. If
agent numbers land via proposals (recommended for numbers over prose), a one-column
addition to the proposal row (the revision id the diff was computed against) plus an
apply-time re-check is the cheap, app-side fix. Do not make venture types translatable:
`langcode`/`default_langcode` are agent-writable identity keys on translatable types (a
verified hole inherited from identity_pillar), and venture numbers have no translation
need.

### Data entry: PARTIAL

Nothing in either repo reads xlsx or csv (`packages/structured-import` parses one
two-column markdown table format and has zero production consumers; no spreadsheet
library exists in either lock file). The honest ranking for ~90 numbers plus 20-30
assumption/fact records updated roughly monthly: (1) a one-off idempotent seed command
loading a checked-in PHP data file, the proven house pattern (`src/Identity/
IdentitySeed.php` + `MigratePillarsCommand::seedFresh`, `src/Pages/PageSeedData.php` +
`PageSeeder`); (2) workspace edit forms for ongoing updates and status flips, needed
regardless; (3) not recommended: an xlsx pipeline. One correction the verification pass
forced: the framework DOES ship a live import platform (`import:run`/`import:status`/
`import:rollback` with id-map idempotency, in the site vendor at alpha.202) that an
app-side CSV source plugin could ride, so a file-refresh path exists at moderate cost if
monthly hand-edits ever grate. It is the middle option, not a necessity. Verdict
unchanged: seed + forms; the xlsx stays outside as the modeling tool.

### Charts: MISSING

No chart library, no canvas, no data-bound SVG anywhere in either repo (15 library names
grepped; the only "chart"-named artifacts are a CSS progress bar and nested text lists).
The analytics module deliberately ships stat cards plus tables, which is the house style:
numbers presented tabularly. If a visual is ever wanted for the roll-up, the cheapest
credible path is inline SVG bars or width-percentage CSS bars generated in Twig, no
dependency; say no to a chart library until someone actually asks for one.

## Cross-cutting verifications

**At rest and in operations, "strictly non-public" holds**, now sourced from the infra
repo rather than config comments: the production SQLite lives on the `fnpi_storage`
volume mounted only into the app container; Caddy's docroot is the separate read-only
public volume and publishes no ports (tunnel-only); nightly encrypted restic backups
cover the volume with 7d/4w/12m/5y retention; each deploy takes a `VACUUM INTO` snapshot
beside the DB; the image is built on the Pi and never pushed to a registry. And the
schema-sync claim is real: `deploy-fnpi.yml:80` runs `db:init --sync-schema`, so new
venture tables land on the sovereign volume on the next deploy, exactly as four prior
entity tools did. Residuals: the restic offsite target lives in `/etc/oiatc/restic.env`
on the Pi (not verifiable from git), and accumulated pre-deploy `.pretx-*.bak` snapshots
have no visible pruning beyond restic's view of them.

**Production account inventory (read-only, 2026-06-11):** exactly two accounts exist,
Russell (uid 1) and Matthew (uid 2), **both administrator**. Editor and Viewer roles are
defined but held by no one. Both rows carry stale stamped permission lists (pre-Drive/
Pages/inbox), harmless only because the administrator short-circuit ignores them; it
proves permissions do not auto-refresh, so any new venture permissions require re-running
`app:assign-role` for non-admin accounts at launch (currently zero such accounts).

**Deletion and orphans:** no cascade machinery exists anywhere (the one cross-entity
guard in the framework is dormant); a multi-entity taxonomy inherits silent orphaning on
delete. Recommendation: no-delete posture for venture entities (archive status instead),
which also fits an append-only evidence trail.

**Provenance against the xlsx:** nothing exists today beyond prose "Prepared:" lines in
two knowledge markdown files. Drive recognizes xlsx for display but the shipped upload
whitelist would reject it (`config/waaseyaa.php:45-54` lacks the spreadsheetml MIME); one
config line makes Drive the workbook-of-record store, with `drive_asset` revisions giving
the workbook identified versions a snapshot entity can pin.

## Recommended shape

**Workspace, not pages.** Pages are the public rendering surface; this content must
never have a public route, a published pointer the public renderer reads, or a place in
the RAG corpus. A workspace module is also simply the proven cheap path: the Analytics
recipe (module entry, controller, Twig template, routes, policy, tests) shipped in one
pass.

**Entity taxonomy: three types, lean.**

1. `venture_lane` (revisionable): label, narrative fields, the 15 scenario values as
   integer cents (`y1_worst` .. `y5_best`), an `assumptions` nested list riding `_data`
   exactly like `page.blocks`, editor attribution fields. One entity per lane makes
   per-lane history free, keeps agent/human edits to different lanes collision-free, and
   sums trivially.
2. `gating_fact` (revisionable): lane reference via the proven uuid-string idiom, label,
   detail, `status` in `{placeholder, confirmed}`, `confirmed_by_uid`/`label`/`at`.
   A separate type because facts are the things whose status flips need per-fact history,
   human-only confirmation, and individual audit rows.
3. `venture_snapshot` (revisionable, optional but cheap): `as_of_date`, `model_version`,
   optional workbook reference (drive_asset uuid + revision). One row per model refresh;
   lanes point at their snapshot. This is what makes "mirror the xlsx" honest: numbers
   on screen always say which workbook state they reflect.

Assumptions ride the lane as a nested list (they change with the lane and do not need
individual status); gating facts get their own type (they do). The roll-up is a
read-model service summing lanes in PHP. Scenario values per cell, not prose. Every
screen carries the placeholder-grade banner until a snapshot says otherwise.

**Access:** new permissions `view ventures` (and `edit ventures`, `confirm ventures`),
granted to administrator implicitly and to staff roles explicitly; a `VentureAccessPolicy`
returning **Forbidden** on view without the permission, registered in both discovery
paths. Neutral entity-type ids. No translatability.

**MCP:** add the three types to the scope allowlists, deny `status`/`confirmed_*` fields
per-type, add per-type numeric guards, and prefer the proposal flow with the apply-time
staleness check for agent number drafts. Publish/confirm stays human exactly as page
publishing does today.

**App vs framework:** the entire recommended path is app-side. Optional framework
increments, each a CI-gated cut and none required: wiring `EntityValidator` into the
kernel repository factory plus a Range constraint arm (declarative save-time validation);
threading the acting account into revision writes (populating the dormant
`revision_author`); fixing the lifecycle audit listener's attribution. These belong in
`docs/waaseyaa-upstream-notes.md` when the build step starts, alongside the verified
quirks this recon surfaced (agent-writable `langcode`/`uuid` on translatable types; the
append-only decorator's raw-SQL passthrough; blank revision timestamps in the Pages and
Documents history panels).

## Decisions for Russell (surfaced, not decided)

1. **Staff-only workspace vs a limited public summary later.** Lean: staff-only, full
   stop, for now. The recon adds one hard fact: anonymous `GET /api` lists every
   registered entity type id, so even the staff-only build should use neutral type names.
   A public summary later would be a separate, deliberately published page derived from
   confirmed figures only, never a loosened policy on the venture entities.
2. **Matt: edit rights or read-only.** The binary is not actually available today. Both
   production accounts are administrator, and the administrator role short-circuits every
   permission check, so "Matt read-only on ventures" is only purchasable by demoting his
   whole account (editor keeps workspace writes but loses destructive admin operations;
   viewer loses all writes everywhere). The real three-way: keep Matt admin (he can edit
   ventures; gating rests on the agent-drafts/human-publishes workflow), demote to
   editor, or demote to viewer. Lean: keep admin, revisit when a third staff account
   appears.
3. **Relationship to the xlsx: mirror or replace.** Lean: mirror, made honest by the
   snapshot entity (as-of date, model version, optional Drive-held workbook of record;
   one MIME-whitelist line enables the upload). Replace would mean rebuilding the model's
   formulas in app code, which nothing in this recon supports building.

## What was and was not verified

All nine matrix entries were adversarially verified against the code except the entity
machinery scout (verifier lost to a transient API error); its load-bearing claims were
re-checked individually: the config-driven registration recipe and `AppEntityTypeLoader`
(read directly), the `administer content` admin-surface gate
(`GenericAdminSurfaceHost.php:49`, read directly), the `_data`/`json_extract` storage
story and the generic published pointer (confirmed by the verified fields and revisions
entries). Corrections the verification pass forced are folded in above: attribute
registration alone does not register a type; the admin surface is permission-gated, not
any-authenticated; agent CRUD over identity_pillar IS live (via the generic entity tools
plus allowlists), making the field-level deny pattern the precedent for human-only
confirms; and a framework import platform exists, softening the "no import tooling"
framing without changing the verdict. Not verifiable from here: the restic offsite
target and any deployed Pi config overrides (both server-side files outside git).
