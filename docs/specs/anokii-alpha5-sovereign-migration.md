# Anokii alpha.5: migrate fnpi onto the canonical package (sovereign tier)

Status: **spec / not started.** Branch `migrate/anokii-shell-alpha4` currently holds
only the inert `waaseyaa/anokii ^0.1.0-alpha.1 -> ^0.1.0-alpha.4` bump (verified: no
behavior/look/data change; all package classes fnpi uses still resolve).

## Why this is alpha.5, not alpha.4

alpha.4 was shaped to rhtcircle (shared-graph public tier). fnpi (sovereign tier,
real member/internal data, FNPI brand) diverges from it at every layer, so a
behavior/look/data-preserving migration needs the package generalized first.

| Layer | fnpi today | Package alpha.4 | Gap |
|---|---|---|---|
| Retriever | `App\CoIntelligence\Retriever`: flat single-vantage over raw `anokii_doc_chunk`, gate **0.45 x max** | `GraphRetriever`: reads `doc_chunk` **entity**, needs a community vantage + place/community/service/project graph, gate **0.5 x max + 1.5 floor**, topic/closeness ranking | different source, gate, and shape -> different passages |
| Prompt | `ChatPromptBuilder` with **`agentSystem()`** (agentic workspace) | no `agentSystem()`; vantage/ChatVoice instead | package lacks the agent prompt |
| Workspace layer | `ConversationRepository`, `AgentConversation`, `AgentTools`, `AgentProposalRepository` | none (by design) | stays app-side |
| Chunk store | raw `anokii_doc_chunk` (chunk_key, source_url, title, heading, text) | `doc_chunk` **entity** (`_data` JSON + entity_type/entity_id) | needs data migration |
| Shell | forked `_shell.html.twig`: top-bar search + bell + user-chip, footer, wolf, Anton/Oswald; user-chip in **top bar** | simpler `_shell`: user-chip in **sidebar**, no top bar/footer/search/bell | package shell is a subset |
| Catalog | `App\Anokii\Modules`: own order, grouping, labels, icons + a `settings` module | `AdminModules`: different order/grouping/labels; Analytics under "Insight"; no `settings` | straight adoption visibly changes the nav |

## The three phases

### 1. Catalog (package + fnpi)
- Package: extend `Anokii\Admin\AdminModules::resolve()` with optional **per-install
  presentation overrides** (label/desc/icon/group/order by id), **extra modules**
  (e.g. `settings`), and a **sovereign live/preview preset** (`AdminModules::sovereign()`:
  live = identity, drive, documents, cointelligence, pages, inbox, venture, ventures,
  analytics; preview = rooms, workspaces, vault, governance, portal). Additive;
  rhtcircle unaffected.
- fnpi: source the nav from `AdminModules` + an FNPI override map that reproduces the
  current nav **byte-identically** (order, groups, "Identity Workspace" etc., Settings),
  then delete `App\Anokii\Modules`. Keep `AnokiiShell` thin (or fold into the override map).

### 2. Shell (package + fnpi; small rhtcircle touch)
- Package: generalize `_shell.html.twig` into a true superset: block-ize the user-chip,
  top bar, and footer; full brand block; **theme-driven fonts** (move the Fraunces/Inter
  hardcode out of `_base` into each install's theme CSS).
- rhtcircle: move its font link into `anokii-rht.css`; re-verify (deploy).
- fnpi: drop the forked `_shell`; add an FNPI theme CSS (dark teal, Anton/Oswald, wolf)
  + fill the top-bar/footer/brand blocks with fnpi's markup; reparent the 15 tool
  templates onto the package block contract. Verify pixel parity on every authed tool page.

### 3. Engine + data (package + fnpi)
- Package: add a **flat single-vantage retrieval mode with a configurable relevance gate**
  (match fnpi's 0.45) and an **agent-prompt seam**, so the package engine can serve a
  sovereign workspace.
- fnpi: point the workspace chat at the package engine; keep the stateful layer
  (conversations, proposals, agent tools) app-side; delete the duplicated
  `App\CoIntelligence` engine pieces.
- **doc_chunk migration** (`app:migrate-doc-chunks`, idempotent):
  1. `N = COUNT(*) anokii_doc_chunk`.
  2. For each raw row upsert a `doc_chunk` **entity** keyed by `chunk_key`
     (`_data` = {source_url, heading, text, entity_type:'', entity_id:''}, label = title).
  3. Assert `COUNT(doc_chunk) == N` (row-count parity), then run `app:ingest` to converge.
- **Rollback:** never drop `anokii_doc_chunk` during migration; reverting `FNPI_REF`
  restores the old engine instantly; the deploy's `.pretx-*.bak` snapshot
  (`VACUUM INTO`, already in `deploy-fnpi.yml`) restores the whole DB; drop the
  `doc_chunk` entity rows only after a verified parity window.

### Login gate
Keep fnpi's existing gate (`AnokiiController` + DashboardGate, allowAll + priority 100,
controller-enforced). The package-canonical login controller is a separate later chip.

## Parity gate (hard; all must hold before deploy)
- `/admin/anokii` still login-gated at priority 100.
- Workspace chat answers grounded; conversations persist.
- `doc_chunk` row-count parity; `app:ingest` idempotent.
- Retriever returns the same passages for a sample question set (single-vantage flat
  mode at gate 0.45).
- Identity / Drive / Documents / Pages / Inbox / Ventures unaffected.
- Anishinaabemowin language-keeper gate intact.
- Provider rebind + `NullLlmProvider` fallback intact.
- Workspace looks identical in the FNPI brand.

## Sequencing
Catalog and shell (phases 1-2) are independent of the engine (phase 3) and can ship
first. Each phase ships behind the parity gate, at fnpi's own `FNPI_REF`, with the
auto DB snapshot. Do **not** touch oiatc until fnpi is proven.
