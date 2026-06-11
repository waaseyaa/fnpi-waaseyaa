# Waaseyaa upstream notes

Running log of framework quirks, gaps, and changes worth remembering when building
FNPI on Waaseyaa. Newest first. These are notes about the *upstream* framework
(`waaseyaa/framework`), kept here so app work does not re-discover them.

## 2026-06-10 — MCP endpoint (alpha.202): three gaps found wiring POST /mcp, all worked around app-side

Found during the MCP wire-up (see `docs/reports/2026-06-10-mcp-wireup.md`).
App workarounds live in `src/Mcp/` + `src/Provider/McpAgentServiceProvider.php`;
each can be deleted when the upstream fix lands.

1. **`McpEndpoint::handle()` returns `McpResponse`, which the SSR dispatcher
   cannot convert — every /mcp request 500s.** `SsrPageHandler::dispatchAppController()`
   only accepts a Symfony `Response` or an Inertia page result; the mcp
   package's `McpResponse` VO falls through to a 500 ("returned an unsupported
   value"). The package's own tests call `dispatch()` directly and never catch
   it. Workaround: the app re-registers the `mcp.endpoint` route
   (`removeRoute()` + `addRoute()`, the documented override lever) onto
   `App\Mcp\McpEndpointController`, which converts `McpResponse` → `Response`.

2. **`AttributeToolRegistry` hydrates from an empty manifest under the HTTP
   kernel — tools/list is always empty.** `AiToolsServiceProvider::resolveManifest()`
   asks the kernel-services bus for `PackageManifest`, but nothing ever serves
   it (`ProviderRegistryKernelServices` knows EntityTypeManager / Database /
   Dispatcher / Logger / PDO + provider bindings only), so the registry falls
   back to `new PackageManifest()` and discovers zero `#[AsAgentTool]` classes
   even though the compiled manifest has all 16. Workaround:
   `App\Mcp\McpToolCatalogue` hand-constructs the tools (mirroring
   `App\CoIntelligence\AgentTools`), reading each class's own `#[AsAgentTool]`
   attribute for metadata. Side effect: the bimaaji introspect/mutation tools
   are skipped here because their deps (`ApplicationGraphGenerator`,
   `MutationValidator`) fail to resolve at request scope in this app;
   `bimaaji_search_specs` constructs fine.

3. **`AbstractAgentTool::argumentsForAudit()` TypeErrors on list-valued
   arguments.** It calls `strtolower($key)` on every array key recursively;
   integer keys (any list value, e.g. a page's `blocks`) throw on PHP 8.4+.
   Workaround: the app's MCP audit trail hashes raw arguments instead of
   calling it.

Also noted, not blocking: the app cannot usefully re-bind `McpAuthInterface`
because provider resolution is first-match in registration order and app
providers register after `Waaseyaa\Mcp\McpServiceProvider` (its empty-token
default wins for the admin `ServerConfigReadModel`); `BearerTokenAuth` does a
plain array lookup (no `hash_equals`) and ignores the account's blocked
status; `McpDispatchAuditListener` listens for `waaseyaa.mcp.dispatch` but
`McpEndpoint` never dispatches it; `McpEndpoint` always advertises
`serverInfo.version` 0.1.0. Already queued upstream per the wire-up brief:
media upload tool, OAuth 2.1 resource-server auth, MCP registry listing,
stale mcp README/spec.

## 2026-06-08 — Published-revision pointer (added upstream, alpha.195)

Built for the Anokii Pages capability (public site driven from `page` entities).

- **Gap found:** the revision layer had a single base-table pointer
  (`revision_id`) that always means *current/latest*. There was no separate
  "published" pointer, so the live view and an in-progress draft could not
  differ. The `workflows` package's `workflow_state` is a flag on the current
  revision, not a second revision pointer.
- **Two parallel revision subsystems exist** — know which one you are on:
  - **`EntityRepository` + `SqlSchemaHandler` + `Driver/RevisionableStorageDriver`**
    — table `<entity>_revision` (single underscore), base pointer column
    `revision_id`, snapshot id column `revision_id`. This is what FNPI content
    entities use (`page`, `identity_pillar`, `document` — all registered
    `revisionable: true` and accessed via `EntityRepositoryInterface`).
  - **`RevisionableSqlBlobStorage` / `RevisionableSqlColumnStorage` + `RevisionRowHydrator` + `Schema/RevisionTableBuilder`**
    — table `<entity>__revision` (double underscore), pointer/snapshot column
    `vid`. This is the newer two-axis (revisionable × translatable) path. FNPI
    does **not** use it.
- **Change made (additive, backward-compatible):** added a nullable
  `published_revision_id` column to the revisionable base table in
  `SqlSchemaHandler::buildTableSpec()`, plus `loadPublishedRevision()` /
  `setPublishedRevision()` on `EntityRepository` and `EntityRepositoryInterface`
  (the vid-based subsystem was intentionally left untouched). The column
  defaults to NULL, so every pre-existing revisionable row is unaffected, and
  `loadPublishedRevision()` returns null on base tables that predate the column.
  The base-table-only pointer columns are skipped by `SqlSchemaHandler::seedRevisions()`.
- **How to use it for `page`:** `find()` / latest revision is the working draft;
  `loadPublishedRevision($id)` is what the public route renders; `setPublishedRevision($id, $rev)`
  is the deliberate "go live" step; publishing an older revision is rollback.

## 2026-06-08 — VERSION file was frozen at alpha.4 (fixed upstream, alpha.195)

The framework's `VERSION` file read `0.1.0-alpha.4` at every tag because no
release step ever wrote it; the real version lives in git tags. A cold clone of
`waaseyaa/framework` therefore misreported its version (use `git describe --tags`,
not the file). Fixed in `release-cut.yml` (and the `scripts/release.sh` fallback):
the cut now stamps `VERSION` to match the tag, starting at alpha.195.

## 2026-06-10 — SSR path resolver: unrouted `/` falls back to `page.html.twig` as an empty 200 shell

The framework's `RenderController::renderPath('/')` tries `home.html.twig` then
`page.html.twig` as template candidates (the candidate chain is hardcoded
upstream), and the SSR Twig env runs with `strict_variables=false`. With FNPI's
hand-coded `home.html.twig` deleted (pages render from published `page`
entities via the routed `PageController`), an UNROUTED `/` would render
`page.html.twig` with no `page` variable: an HTTP 200 empty shell rather than
a 404. Only reachable if the `home` route fails to register (provider
discovery broken) — the routed path is unaffected. The other public paths
(`/technology` etc.) correctly 404 when unrouted now that their hand-coded
templates are gone; this also closed the /proof-style stale-fallback hole for
those URLs. Nothing the app can change; would need an upstream option to
disable the path-template fallback (or strict candidates) per app.

## 2026-06-10 — SecurityHeadersMiddleware is spec'd into the pipeline but never wired (X-Frame-Options DENY, dormant)

The framework ships `packages/foundation/src/Middleware/SecurityHeadersMiddleware.php`
(`X-Frame-Options: DENY` on every response, `#[AsMiddleware(pipeline: 'http',
priority: 100)]`), and both `docs/specs/middleware-pipeline.md` and
`docs/specs/security-defaults.md` document it as part of the HTTP pipeline —
but `HttpKernel` (vendored alpha.202) builds the pipeline only from its
hardcoded middlewares plus `HasMiddlewareInterface` providers, so the class is
compiled into the manifest and never instantiated. Consequence: every response
is frameable today, which the Anokii workspace RELIES on (the Documents inline
PDF iframe, and the planned right-rail preview panel that will iframe the
Pages draft preview). If an upstream cut ever closes this spec-vs-kernel
drift, `DENY` lands on every response and silently breaks all in-workspace
iframes. Before that happens, the wiring needs an embed-route exemption hook
(planned as part of the workspace preview-panel framework increment, F3 in the
shell-redesign track). Until then: do not "fix" the drift upstream without the
exemption, and treat any framework release note touching SecurityHeaders as a
deploy blocker for the workspace.
