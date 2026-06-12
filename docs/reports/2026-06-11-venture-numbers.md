# Venture Numbers: staff-only, chat-first, live (2026-06-11)

Step 02 of the venture-numbers track, building on the orientation matrix in
[2026-06-11-venture-numbers-orientation.md](2026-06-11-venture-numbers-orientation.md).
All decisions locked by Russell 2026-06-11: staff-only with no public summary, Matt edits
through the UI, the xlsx stays the modeling tool with Anokii as the mirror, increments
graded against the native-spreadsheet trajectory, chat as the primary surface.

**Shipped and deployed:** fnpi-waaseyaa main `813c796` (merge of `c34e44c`), infra
`90436af` (FNPI_REF bump), deploy run 27387243321 green. Prod seeded
(`app:seed-ventures`: 6 lanes, 6 gating facts, 1 snapshot) and verified end to end,
including a live chat query computing the roll-up. Suite: 191 tests, 1442 assertions.

**Every figure in this section is placeholder-grade and renders behind a banner saying
so.** The seeded numbers anchor to the workbook brief; intermediate years are labeled
placeholder ramps. Matt and Russell correct cells in the UI; that is the point of the
mirror.

## Matrix deltas (the two new rows; the rest stands per the orientation report)

| Capability | Status before | What closed it | Trajectory |
|---|---|---|---|
| Matt-editable | PARTIAL (no venture UI) | Inline grid cells, assumptions editor, one-click gating-fact confirm at /anokii/ventures | On-path: editable cells in an entity-backed grid are the proto-spreadsheet primitive |
| Chat-first operation | PARTIAL (cowork pattern existed for Identity only) | The section IS a cowork surface: Co-Intelligence pane + lane-card rail, proposals preview on the cards, agent answers numbers from the entities | On-path: this is the shell decision's target shape, applied to a second tool |

Every increment was app-side; zero framework cuts were needed, exactly as the
orientation matrix predicted. Nothing built here is throwaway against the
native-spreadsheet direction: the entities are typed per-cell values with revision
history, the grid UI is entity-backed editable cells, and the roll-up is a computed
read-model. The one deliberately disposable piece is hand-rolled grid JS (dirty
tracking, save batching), which a future framework spreadsheet primitive would replace.

## What was built

**Entities** (config/entity-types.php, all revisionable + revisionDefault; tables landed
on deploy via db:init --sync-schema):

- `venture_lane` ([VentureLane.php](../../src/Entity/VentureLane.php)): key, title,
  summary, the scenario grid as fifteen top-level integer fields `y1_worst .. y5_best`
  (whole CAD per year; top-level so chat proposals diff field by field), assumptions
  (nested list), notes, editor attribution. Per-lane history is free.
- `gating_fact` ([GatingFact.php](../../src/Entity/GatingFact.php)): lane_key, label,
  detail, status placeholder|confirmed, and `confirmed_by_uid/_label/_at` stamped on the
  confirm flip and cleared on the way back. The revision log carries
  "Confirmed by <name>"; the placeholder-to-confirmed trail is the fact's history.
- `venture_snapshot` ([VentureSnapshot.php](../../src/Entity/VentureSnapshot.php)):
  as_of + model_version + note; what lets every screen say "mirrored from
  FNPI-revenue-model.xlsx, as of 2026-06-11" instead of showing undated numbers.

**Access** ([VentureAccessPolicy.php](../../src/Access/VentureAccessPolicy.php)): the
first permission-gated READ in the workspace. `view ventures` gates view and denies
with **Forbidden, not Neutral**, per the orientation finding (Forbidden is the only
result the entity query layer drops rows on, so this contains JSON:API, GraphQL,
admin-surface, agent tools, and any future query consumer in one place). `edit
ventures` gates writes, `confirm ventures` gates the status flip (checked as the
'confirm' operation), `administer ventures` gates delete, which no UI exposes
(no-delete posture; archive by status if ever needed). Roles: Editor carries
view+edit+confirm (the Matt bar; both real accounts are administrator today and
short-circuit anyway); Viewer carries none, so a future non-staff account sees nothing.

**Surface** (/anokii/ventures, [ventures.html.twig](../../templates/anokii/ventures.html.twig),
[VenturesController.php](../../src/Controller/VenturesController.php),
[VentureService.php](../../src/Venture/VentureService.php)): the Identity Workspace
cowork template, per the shell decision. Chat pane on the left (its own thread key,
survives the post-apply reload); the rail carries the placeholder banner, the computed
roll-up table with the Technology-share line, and one card per lane: editable 3x5 grid
(whole dollars, dirty-cell highlighting, one Save per lane), assumptions list with an
edit toggle (one per line), gating facts with status pills, confirm/revert buttons, and
per-fact history, plus per-lane history. Roll-ups recompute live after every save.
There is no public route, no ingestion, and no RAG exposure: venture numbers reach chat
only through policy-gated entity tools.

**Chat** ([AgentTools.php](../../src/CoIntelligence/AgentTools.php),
[AgentConversation.php](../../src/CoIntelligence/AgentConversation.php),
[ChatPromptBuilder.php](../../src/CoIntelligence/ChatPromptBuilder.php)): the venture
types joined the agent's workspace scope (reads run inline under the same policy
handler as the UI, so a viewer-role account gets refusals); proposals targeting a lane
or gating fact preview as a draft on the card with Approve/Reject in place; approving a
gating-fact confirm stamps the approver as the confirmer, exactly like the UI button.
The agent prompt teaches the grid fields, the whole-dollar convention, and the hard
placeholder rule (never present a venture figure as confirmed; always label
placeholder-grade).

**MCP** ([McpAgentScope.php](../../src/Mcp/McpAgentScope.php)): the three types joined
the remote agent's read and write allowlists. The gating-fact status flip and its
confirmation stamp are denied per-type over MCP: the remote surface has no approval
loop, so confirmation stays human (in the UI or via an approved chat proposal). The
global publish-pointer denials cover the new types automatically.

**Seed** ([VentureSeed.php](../../src/Venture/VentureSeed.php), `app:seed-ventures`,
idempotent, never overwrites entered numbers): the six lanes with the locked anchors.
Likely roll-up: Yr 1 284,500 (brief says roughly 270k), Yr 5 3,518,500 (roughly 3.5M),
Technology 87% of likely Yr 5; all three pinned by tests. Gating facts: four on Faraday
(landed cost, SKU split, independent test data, current sell-through), Matthew's deal
history on Sourcing, the DAF EOI outcome on Defence; all seeded placeholder.

## Verification

- Suite green: 191 tests, 1442 assertions (the venture-numbers tests cover entity
  capabilities, the Forbidden-not-Neutral access posture, the roll-up anchors, routes,
  signed-out handling, the module, MCP scope, the agent prompt contract, and the
  template render including the dash sweep).
- Local E2E (built-in server, cookie-jar logins): a viewer-role account got 403 on the
  page, 403 on lane save, 403 on fact confirm; an editor-role account rendered the full
  surface, edited a scenario cell (revision recorded, attributed, roll-up recomputed
  live), confirmed and reverted a gating fact (confirmed_by stamped then cleared, both
  revisions in the fact's history). Local gotcha for next time: the PHP built-in server
  resolves the relative WAASEYAA_DB against the docroot, so exporting an absolute path
  is required or a stray empty DB appears under public/.
- Prod (post-deploy): signed-out /anokii/ventures 302s to the login page; the seed
  printed 13 rows and re-running it skips everything; a live chat probe (agent loop as
  uid 1 inside the container, probe conversation deleted afterwards) answered the
  roll-up question from the entities: likely Yr 5 total $3,518,500 with Technology
  ~$3.06M at ~87%, every figure labeled placeholder-grade, rendered as a table.

## Deferred, and why

- **Concurrency control**: writes remain last-write-wins (no optimistic locking exists
  framework-wide); disjoint-field merges keep lane collisions rare and revision history
  is the recovery net. The cheap app-side staleness check (proposal stores the
  revision id it diffed against; apply re-checks) is the first hardening increment if
  agent and human edits ever collide in practice.
- **True draft invisibility for MCP writes**: a remote agent lane write becomes the
  current visible revision (historied, never published anywhere public). Wiring the
  published-revision pointer for venture types would give Pages-style draft/publish;
  deferred until the remote-agent workflow actually wants it. In-app chat already has
  the human gate via proposals.
- **Charts**: nothing exists in either repo (orientation finding); the roll-up is a
  table. Inline SVG bars are the cheap path if anyone asks.
- **xlsx import**: not built, per the locked decision; the seed file plus UI editing is
  the mirror workflow. The framework's import:run platform exists if a CSV refresh path
  is ever wanted.
- **Framework increments** (all optional hardening, none needed): save-time validator
  wiring, revision_author threading, lifecycle-audit attribution. Logged in the
  orientation report for docs/waaseyaa-upstream-notes.md when relevant.

## Flags for Russell

1. **The numbers are mine to anchor, Matt's and yours to correct.** Yr 2 to Yr 4 cells
   are interpolated placeholders; the per-lane assumptions record the workbook anchors
   verbatim so nothing is lost as you overwrite cells in the UI.
2. **A parallel session shipped the venture tracker in the same merge** (its own report:
   [2026-06-11-venture-tracker.md](2026-06-11-venture-tracker.md)). The two tools are
   independent (different types, policies, routes, cards). Running two sessions on one
   working tree converged safely this time, but it is a collision hazard worth avoiding.
3. **A barred community name appeared once in the tracker report's first commit.** The
   tip was amended and force-pushed the same hour (`c130ddf` replaced by `4fce92c`);
   the name is gone from the file and from all reachable history. Same caveat as the
   June photo purge: GitHub may serve the orphaned pre-rewrite commit to anyone who
   already knows its SHA until a support-side cache purge.
4. **Chat edit parity**: approving a chat proposal that flips a gating fact to confirmed
   records the approver as the confirmer. That equivalence (approval = confirmation) is
   deliberate; say the word if you want confirmation to be UI-button-only.
