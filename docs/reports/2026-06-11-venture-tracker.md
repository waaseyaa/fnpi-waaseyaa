# Venture tracker in Anokii (2026-06-11)

The venture tracker is now entity-backed, staff-visible at `/anokii/venture`, and
MCP-updatable: the first real workload for the Workspaces concept. Read-only v1 surface; the
data lives in the workspace and the Co-Intelligence agent maintains it.

## Entity types

Two flat, **non-revisionable** content entities (config/entity-types.php), deliberately not
revisionable: the tracker is a live board edited in place, not a draft/publish artifact.

- **`venture_thread`** (`App\Entity\VentureThread`): `title`, `sort_order`, `next` (the
  per-thread footer line).
- **`venture_item`** (`App\Entity\VentureItem`): `thread_uuid`, `body`, `status`
  (done | doing | wait | todo | dec), `who`, `sort_order`, `created_at`, `updated_at`.

Tables materialized by the deploy's `db:init --sync-schema`.

## Access policy

`App\Access\VentureTrackerAccessPolicy` (wired into `WorkspaceAccess::handler()`), distinct
from the stricter `VentureAccessPolicy` that guards the separate Venture **Numbers** section.
Posture: any signed-in workspace account (Russell, Matthew) may read and write; anonymous
and unauthenticated fail closed (Neutral). No public surface, no per-op permission tier.
Pinned by test (anon denied / staff allowed for view, update, delete, create on both types).

## MCP agent scope

Both types added to `McpAgentScope::READ_TYPES` and `WRITE_TYPES`, and to
`AgentTools::WORKSPACE_TYPES` (the in-app agent). The agent is the tracker's maintainer; it
carries no personal third-party data. Capability stays `tool.entity.*`. This is the
deliberate exception to the "writable types are revisionable" rule (the comment now records
it): a non-revisionable write mutates live, which is what a board wants. Publish/revision
fields stay refused even so. Pinned by test (agent can read and write both; `venture_item.status`
writable; `published_revision_id` still refused).

## Surface

- Route `anokii.venture` GET `/anokii/venture` (`App\Controller\VentureController`), inside
  the authed workspace: signed-out redirects to `/anokii/login`; reads threads (ordered)
  with their items (ordered), renders `templates/anokii/venture.html.twig`. Read-only.
- Dashboard card "Venture Tracker" in the Workspace section (`Modules.php`), live, ->
  `/anokii/venture`.
- Template visually matches the source: dark cards in an auto-fit grid, the legend, status
  dots (green done / cyan doing / amber wait / grey todo / red dec), muted who-notes, the
  "Next" footer per card. Mobile: the shell collapses to one column at 880px and the cards
  stack; verified legible at a true 375px viewport.

## Seed

Parsed from the source tracker HTML (copied in as `seed-tracker.html`, **deleted after
seeding, never committed**). Seeded counts on prod, matching the source exactly:

| # | Thread | Items | Statuses |
|---|---|---|---|
| 1 | fnprocure.ca copy refresh | 26 | dec, doing, done, todo |
| 2 | NACCA | 6 | done, todo, wait |
| 3 | INDGEN security + edge server | 10 | doing, done, todo, wait |
| 4 | FN datacenter (Sovereignty Ladder) | 14 | dec, doing, done, todo |
| 5 | Tsenawt (model + possible partner) | 4 | dec, done, todo |
| 6 | Waaseyaa framework / adoption | 15 | dec, doing, done, todo |
| 7 | Open decisions | 5 | dec |

**7 threads, 80 items** (== the source's 80 `<li>` count). The directive exception is
applied: the datacenter convener item seeds as exactly *"Convener: approach Mississauga FN
or Serpent River via the Waasmoowin PM"*, without its parenthetical rationale (verified on
prod: the rationale text is absent).

## Verification

- Suite green: 191 tests, 1442 assertions (includes the 6 new venture-tracker tests:
  registration + non-revisionable, the access matrix, MCP read+write, signed-out redirect,
  the live nav module, and the template render with status dots and who-notes).
- Code is live on prod (main `813c796`, already deployed; the venture tables were created by
  that deploy's schema sync). No public page changed, no re-ingest run.
- **Public index untouched:** 0 chunks sourced from the tracker; total still 144 chunks; the
  index sources remain the five public pages + `/anokii/identity` (pillars). `app:ingest-knowledge`
  reads only pages, pillars, and the knowledge docs, never venture entities, so the staff
  board cannot leak into the public RAG corpus.
- Screenshots (looked at), `storage/framework/_shots/venture/`: `venture-prod-desktop.png`
  (the card grid with dots and who-notes) and `venture-prod-375.png` (single-column stack,
  legible). Captured via a throwaway prod staff account (deleted after; 2 real accounts
  remain, tracker rows intact).

## Note on the concurrent build

A parallel session built the Venture **Numbers** section on a same-named branch and merged
it (commit `813c796`) while this tracker work was in the shared tree; both tracks landed in
that one merge. They are independent: distinct entity types (`venture_lane`/`gating_fact`/
`venture_snapshot` vs `venture_thread`/`venture_item`), distinct policies, distinct routes
(`/anokii/ventures` Numbers vs `/anokii/venture` Tracker), and two dashboard cards ("Venture
Numbers" and "Venture Tracker"). Suite green with both.
