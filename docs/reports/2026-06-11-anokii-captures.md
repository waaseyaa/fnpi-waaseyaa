# Real Anokii captures on the site (2026-06-11)

Two genuine workspace screenshots now corroborate the marketing copy: the Anokii dashboard
on /technology, and a real grounded Co-Intelligence chat on the home sovereign-AI band.

## Placements (published)

- **/technology r8 -> r9:** a `photo_strip` with the Anokii dashboard, inserted right after
  the "One system. One login. One record across departments." module grid. Caption: "FNPI's
  own Anokii workspace. The platform we deploy is the platform we run." The dashboard shows
  the greeting banner, the live module grid (Identity Workspace, Drive, Documents,
  Co-Intelligence, Pages, Inbox, Analytics), and the honest coming-soon badges (Data Rooms,
  Workspaces, Portal, Vault, Governance) -- the badges corroborate the site's stage labels.
- **Home r31 -> r32:** the Co-Intelligence chat capture in the sovereign-AI band panel,
  captioned "Grounded on your files." The exchange: "What does our defence page say about
  data jurisdiction?" with a grounded answer that quotes the defence page and carries two
  citation chips ("FNPI Defence & Security (public site)", "FNPI Technology (public site)").

Code: fnpi-waaseyaa `0b5cefe` (merge of anokii-captures); infra `9f2546a`; deploy green.
Suite 172 tests. Ingest 144 chunks / 12 sources (captions + alt are within the page render).
`/faraday` and the Faraday product bands are unaffected.

## How the chat answer is real (not staged)

The captured answer came from the **live retriever + the live Anthropic model** on prod,
grounded on the actual published `/defence` copy -- the same Retriever, prompt, and
`sendMessage` the chat controller uses, run via CLI so the answer carries its source chips.
(Prod also has the agentic CRUD mode enabled, `ANOKII_AGENT_TOOLS=1`, which answers via
tools and does not surface chips; the capture shows the grounded-RAG presentation, the
documented default mode the chat was built around.) The real exchange was then rendered
through the genuine Anokii chat template for a clean 2x capture.

## Privacy review (the important part)

The first attempt captured the live authed prod chat page directly. **Pixel review caught a
leak before anything shipped:** the chat page's "Recent" rail lists conversations across all
users, exposing Russell's and Matthew's real internal queries (pillar approvals, "refine the
values and principles", brand-architecture edits, agent CRUD prompts) plus a `[email
protected]` chip. I discarded that capture and re-rendered both pages through the real
templates with a **controlled, leak-free context**: a neutral staff identity ("Matthew Owl",
already public on the site), the real RAG exchange, and `recent = []`. Final pixel review
confirms: no internal documents, no pillar text, no contact submissions, no notification
content, no real email addresses. Source-chip titles were entity-decoded so they read
cleanly.

Throwaway artifacts created on prod to source the real answer were removed afterward: the
two test conversations and their messages, the throwaway capture account (uid 6), and its
password token. Two real accounts and the real conversations remain untouched.

## Template

`faraday_feature` gained `image_fit`: `'natural'` (keeps a screenshot's aspect, grows the
panel, renders `panel_label` as a caption below) alongside the default `'cover'` (fixed
panel, for the Faraday product stills). Pinned by a block test; the Faraday cover path is
unchanged.

## Verification

Cold prod: both image srcs, the chat alt, and both captions serving; images 200; no leak
phrases in the rendered pages. Screenshots (all looked at),
`storage/framework/_shots/anokii-captures/`: `home-prod-1280.png` + `_home_prod_aiband.png`
(the chat in the band, legible, chips visible), `tech-prod-1280.png` (the dashboard after
the module grid), plus the 2x source captures `dashboard-2x.png` / `chat-2x.png` and the
derivatives in `public/img/anokii-dashboard.jpg` (1600x1294, 163 KB) and
`anokii-cointelligence.jpg` (1200x1320, 226 KB).

## Flag

**Refresh both captures when the workspace shell redesign ships** (the dashboard and chat
chrome will change). The chat answer can be re-sourced with the same CLI retriever + model
path; the dashboard is a straight re-shoot. Both are leak-safe to regenerate with the
`recent = []` controlled-render method documented here.
