# MCP endpoint wire-up — 2026-06-10

Wires the framework's MCP endpoint (`POST /mcp`, Streamable HTTP, JSON-RPC 2.0)
so an external AI agent can manage Anokii workspace content as **unpublished
drafts**. Publish stays human-only. App wiring only; no framework changes.
Tested end-to-end locally; **not deployed** — Russell confirms Matt's
draft-review window is clear before any prod deploy (see §Deploy).

## Installed versions

- Site: `fnpi-waaseyaa`, `waaseyaa/framework` **v0.1.0-alpha.202** (every
  `waaseyaa/*` package in `composer.lock` is v0.1.0-alpha.202).
- The recon's claims verified against this tree: `packages/mcp` does ship the
  routed endpoint (`McpEndpoint` + `McpRouteProvider`: `POST|GET /mcp`
  csrf-exempt, `GET /.well-known/mcp.json` public), `AgentToolRegistryBridge`
  forwards the auth-resolved account into every tool's
  `AbstractAgentTool::requireCapability()`, the default auth binding is
  `BearerTokenAuth(tokens: [])` (nothing connects until the app provides
  tokens), and `McpController` + `Tools/*` are unrouted legacy. The
  `packages/ai-tools` entity tools exist with `tool.entity.*` capabilities,
  destructive flags, and dry-run support.
- **Recon corrections:** (1) `ai-tools` ships nine entity tools, not six — also
  `entity.list_revisions` (read), and `entity.set_current_revision` +
  `entity.rollback`, which share the `tool.entity.update` capability and so
  cannot be excluded by capability alone. (2) "Re-bind `McpAuthInterface`" is
  not sufficient on alpha.202 — see §What was bound. (3) Three framework-level
  defects had to be worked around app-side — see §Framework gaps.

## What was bound where

New provider **`App\Provider\McpAgentServiceProvider`** (registered in
`composer.json` `extra.waaseyaa.providers`; run `optimize:manifest` after
deploy):

| Binding / hook | What it does |
|---|---|
| `routes()` | Re-registers the `mcp.endpoint` route (`removeRoute` + `addRoute`, the documented override lever) onto `App\Mcp\McpEndpointController`, which converts the package's `McpResponse` into a Symfony `Response` (upstream gap #1 — without this every `/mcp` request 500s). |
| `singleton(McpEndpoint::class)` | Constructs the endpoint with the app's auth + guarded registry. Nothing else binds this class, so the SSR controller resolver picks it up unambiguously. |
| Auth (inside the endpoint) | `BearerTokenAuth([WAASEYAA_MCP_AGENT_TOKEN => mcp-agent user])`. Token comes from env/secret config only. Token unset, account missing, account blocked, or account ever granted the `administrator` role → empty token map → every request 401s (fail closed). |
| `singleton(McpAuthInterface::class)` | Same auth instance, for completeness. Note: the framework's own empty default still wins first-match for the admin `ServerConfigReadModel` (provider-order quirk, cosmetic — the endpoint never resolves the interface from the container). |
| Guarded registry | `App\Mcp\GuardedAgentToolRegistry` over `App\Mcp\McpToolCatalogue` (hand-built; upstream gap #2 means the framework registry is empty over HTTP). Every tool is wrapped by `App\Mcp\GuardedAgentTool`: scope guard → delegate → audit. Adds transport-level `dry_run: true` support (the MCP bridge only ever calls `execute()`). |
| `singleton(RecentInvocationsQueryInterface::class)` | `App\Mcp\McpRecentInvocationsQuery` backs the admin per-tool recent-invocations table from the audit log (the optional ai-observability adapter doesn't exist in alpha.202). |
| CLI `mcp:agent-account` | Idempotently creates/resets the service account (below). |

## Agent account

`mcp-agent@fnprocure.ca`, **uid 5** locally (uid will differ on prod — the
deploy step prints it). Verified at the DB level:

- `roles: []` (never `administrator`, which short-circuits every check)
- `permissions:` exactly `tool.entity.read`, `tool.entity.list`,
  `tool.entity.search`, `tool.entity.create`, `tool.entity.update`,
  `bimaaji.read` — nothing else
- `pass: NULL` — no password exists, so the account can never sign in
  interactively; the bearer token is the only way in

## The publish hole, confirmed and closed

Decision 1 asked for verification that status/published manipulation is
actually blocked. It was **not** — two real holes, both closed app-side in
`App\Mcp\McpAgentScope` (guard runs before the framework tool, on execute and
dry-run):

1. **Field-level (confirmed live):** `EntityCreateTool` passes `values` into
   the entity constructor and `EntityUpdateTool` calls `$entity->set()` for
   every supplied key; `EntityRepository::save()` hands `toArray()` to
   `SqlStorageDriver::write()`, which writes any key whose column exists
   straight to the base table — and `published_revision_id` (the live-view
   pointer) **is** a base-table column on `page`. `values: {published_revision_id: N}`
   would have put revision N live. The per-entity `EntityAccessHandler` hook
   exists on the tools but is a no-op unless attached, and attaching the
   workspace policies would deny the agent *all* writes (they require
   `edit pages` etc., which the locked capability list excludes) — so the
   guard denies the fields instead: `published_revision_id`, `revision_id`,
   `published`, `moderation_state` on every type, plus `status` on `page`
   (its publish marker; `identity_pillar.status` is the maturity field and
   stays writable).
2. **Tool-level:** `entity.set_current_revision` and `entity.rollback` ride
   the granted `tool.entity.update` capability. `set_current_revision` writes
   a historical revision row back over the base row — and revision snapshots
   can carry a stale `published_revision_id` with them; `rollback` clobbers
   the draft head (e.g. the pending copy-refresh drafts). Both are human-only:
   excluded from the catalogue (callers get `Unknown tool`) **and** denied by
   name in the guard if they ever reappear.

Additionally, the coarse capabilities are global per entity type — they would
let the agent read/write the `user` entity (`roles`/`permissions`/`pass` live
there: instant escalation, defeating publish-denial via admin takeover). The
guard scopes **reads** to the five workspace types (`page`, `identity_pillar`,
`document`, `document_note`, `drive_asset`) and **writes** to the four
*revisionable* ones (no `document_note` — it isn't revisionable, so a write
would mutate the live note thread instead of producing a reviewable draft).

The account's grants are exactly the locked list; the guard is enforcement on
the MCP surface, not an expansion of grants. The in-app Co-Intelligence agent
(`App\CoIntelligence\AgentTools`) is untouched — it builds its own tool
instances with the workspace policies attached.

## Audit logging

The framework's audit substrate (`waaseyaa/audit`, append-only `audit_event`)
ships an `McpDispatchAuditListener`, but `McpEndpoint` never dispatches the
event it listens for — so MCP invocation logging was **off** with no framework
writer. Enabled app-side: `App\Mcp\McpInvocationAuditor` writes one
`mcp.dispatch` row per tool invocation via `AuditWriterInterface` —
account uid, `/mcp/tools/<name>` subject, outcome (`allowed`/`denied`/`error`),
severity, dry-run flag, summary, and a SHA-256 **hash** of the arguments
(never raw values — same privacy posture as the framework listener; workspace
content may be confidential). `McpRecentInvocationsQuery` surfaces these in
the admin per-tool detail. Best-effort by contract: an audit failure never
breaks the call.

Sample rows from the acceptance run (local):

```
#55 uid=5 /mcp/tools/entity.create  outcome=allowed dry_run=true  summary=Dry-run: would create page entity
#57 uid=5 /mcp/tools/entity.create  outcome=allowed dry_run=false summary=Created page/6
#59 uid=5 /mcp/tools/entity.update  outcome=allowed dry_run=false summary=Updated page/6
#60 uid=5 /mcp/tools/entity.update  outcome=denied  dry_run=false summary=publish_denied
#64 uid=5 /mcp/tools/entity.delete  outcome=denied  dry_run=false summary=forbidden
#66 uid=5 /mcp/tools/entity.update  outcome=denied  dry_run=false summary=out_of_scope
```

## Acceptance evidence (local dev, 2026-06-10)

Re-runnable: `WAASEYAA_MCP_AGENT_TOKEN=… php bin/maintenance/mcp-smoke.php <base-url>`
(non-destructive; `--write` adds the real draft create/update leg). Full run
passed 15/15 checks. Raw evidence:

1. **Unauthenticated rejected** — `POST /mcp` (no header) →
   `HTTP 401 {"jsonrpc":"2.0","error":{"code":-32001,"message":"Unauthorized"},"id":null}`.
   Same for a wrong 64-hex token.
2. **tools/list with the token** → exactly: `entity.read`, `entity.list`,
   `entity.search`, `entity.list_revisions`, `entity.create`, `entity.update`,
   `entity.delete`, `bimaaji_search_specs`. (`set_current_revision`/`rollback`
   absent by design; bimaaji introspect tools absent due to upstream gap #2's
   dep-resolution side effect; `entity.create`/`entity.update` advertise the
   `dry_run` property.)
3. **entity.create dry-run** → `{"would_create":{"entity_type":"page",…}}`,
   no row written (page table count unchanged).
4. **entity.create real → unpublished draft visible in admin** — created
   `page/6` (`result: 1`); DB: `id=6 rev=1 published_rev=NULL`; signed-in
   `/anokii/pages` listed it with the **"Not published"** badge:
   `<span class="pstate unpub">…Not published</span>` next to
   `MCP wire-up test page`. Public site: `GET /` unchanged (no test content),
   `GET /mcp-wireup-test` → 404 (drafts are never served).
5. **entity.update on the draft** → revision 2 created
   (`revision_log: MCP wire-up acceptance test update`), title updated,
   `published_revision_id` still `NULL`.
6. **Every publish route denied** (each call returned the error envelope and
   wrote a `denied` audit row; pointers verified unchanged after):
   - `entity.update values:{published_revision_id:1}` → *"field(s)
     [published_revision_id] control publish/revision state and are
     human-only"*
   - `entity.update values:{status:"published"}` → same, `[status]`
   - `entity.update values:{revision_id:1}` → same, `[revision_id]`
   - `entity.create values:{…published_revision_id:1}` → same
   - `entity.set_current_revision` → `-32602 Unknown tool` (excluded surface)
   - `entity.rollback` → `-32602 Unknown tool`
   - No publish-capable tool exists in tools/list; the app's only publish
     paths (`POST /anokii/pages/{id}/publish|rollback`) require a session +
     `publish pages`, which the agent account cannot obtain (no password, no
     roles).
   - Bonus escalation checks: `entity.read user/1` and
     `entity.update user/5 values:{roles:["administrator"]}` → *"the MCP agent
     may only read/write workspace content (…), not \"user\""*;
     `bimaaji_propose_mutation` (needs `bimaaji.mutate`) unavailable;
     `bimaaji_search_specs` (needs `bimaaji.read`) works.
7. **entity.delete denied** → *"Account 5 is not permitted to call
   tool.entity.delete"* (capability denial, proving the account simply lacks
   it).
8. **Server card** — `GET /.well-known/mcp.json` → 200, advertises
   `endpoint:/mcp`, `transport:streamable-http`, `authentication:bearer`.

**Pre-existing content untouched:** page pointers and revision counts were
snapshotted before and verified after — `page 1 rev=4/pub=2` (the pending
copy-refresh draft state), pages 2–4 `1/1`, `page_revision=7`,
`identity_pillar_revision=22`, `drive_asset_revision=10` — all identical. The
test page draft and the throwaway local reviewer account used for the admin
screenshot-equivalent were removed afterwards; the audit trail (append-only)
retains the run's rows, as it should.

Unit tests added (`tests/Unit/Mcp/`): scope guard field/type/tool denials and
the dry-run routing — full suite green (130 tests, 719 assertions).

## Framework gaps found (alpha.202)

Logged in `docs/waaseyaa-upstream-notes.md` (2026-06-10 MCP entry) with the
app workarounds to delete when fixed upstream:

1. **`/mcp` 500s out of the box** — `McpEndpoint::handle()` returns
   `McpResponse`, which `SsrPageHandler::dispatchAppController()` cannot
   convert. The endpoint was unreachable in any real app.
2. **Tool discovery is empty over HTTP** — nothing serves `PackageManifest`
   on the kernel-services bus, so `AttributeToolRegistry` hydrates from an
   empty fallback manifest; `tools/list` would be `[]` even with auth fixed.
3. **`AbstractAgentTool::argumentsForAudit()` TypeErrors** on list-valued
   arguments (`strtolower()` on integer keys) — crashes any audit transform of
   real-world args (e.g. a page's `blocks`).
4. Smaller: app `McpAuthInterface` re-bind loses first-match resolution to the
   framework's empty default (admin server-config shows no clients);
   `BearerTokenAuth` compares tokens by array lookup (no `hash_equals`) and
   ignores the account's active/blocked flag; `McpDispatchAuditListener`'s
   event is never dispatched by the endpoint; entity-tool capability gating
   has no per-entity-type or per-field dimension (the whole reason the app
   guard exists); the bimaaji introspect tools' deps don't resolve at request
   scope here.
5. Already queued upstream per the brief: media upload tool, OAuth 2.1
   resource-server auth, MCP registry listing, stale mcp README/spec.

## Prod deploy (prepared, NOT executed)

**Gate: do not deploy until Russell confirms Matt's draft-review window is
clear.** The 7 pending copy-refresh page drafts and all published content are
not touched by this change (no schema migration; the only DB write is the new
`user` row, and `audit_event` rows as the endpoint is used).

1. Merge this branch's commit to `main`; bump `FNPI_REF` to it in
   `waaseyaa-infra` (standard flow, `waaseyaa-infra/runbooks/05-…`).
2. Generate a **new** production token (do not reuse local):
   `openssl rand -hex 64`-style, 64 hex chars. Store it as
   `WAASEYAA_MCP_AGENT_TOKEN` in the secret store (ansible vault →
   app env), never in the repo. The local dev token lives only in the
   gitignored `.env` here.
3. Deploy; then on the Pi (one-time, idempotent):
   `vendor/bin/waaseyaa optimize:manifest` (picks up the new provider — the
   deploy flow's normal manifest rebuild covers this) and
   `vendor/bin/waaseyaa mcp:agent-account` (prints the prod uid).
4. Smoke: `WAASEYAA_MCP_AGENT_TOKEN=… php bin/maintenance/mcp-smoke.php https://fnprocure.ca`
   — non-destructive; expect 15/15 PASS. Optionally `--write` once, then
   delete the printed test page id via the Pages admin (requires
   `administer pages`) or leave it as an unpublished draft.
5. Verify `https://fnprocure.ca/.well-known/mcp.json` serves the card and an
   unauthenticated `POST /mcp` 401s.

**Rollback:** revert the `FNPI_REF` bump and redeploy — the route override,
provider, and guard disappear with the code; `/mcp` returns to its prior
(broken-500/fail-closed) state. The `mcp-agent` user row and audit rows are
inert data and can stay; to disable the endpoint *without* a redeploy, unset
`WAASEYAA_MCP_AGENT_TOKEN` (or block the account / strip its permissions:
`mcp:agent-account` re-grants if needed later) — empty token map means every
request 401s. Token rotation = replace the env value and restart the app
process.

## Connecting a client (for later)

Streamable HTTP MCP server: URL `https://fnprocure.ca/mcp`, header
`Authorization: Bearer <token>`. JSON-RPC methods: `initialize`, `ping`,
`tools/list`, `tools/call`. Write tools accept `dry_run: true` for a
side-effect-free preview. The agent should treat `isError` envelopes with
`human-only` / `out_of_scope` / `not permitted` messages as hard policy, not
retryable errors.
