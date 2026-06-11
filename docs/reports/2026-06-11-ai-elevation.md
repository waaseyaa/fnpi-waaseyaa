# AI elevation + remaining content fixes (2026-06-11)

Follow-up to [2026-06-11-contact-hero-rewrite.md](2026-06-11-contact-hero-rewrite.md).
Russell's call: AI is at the core of everything; promote it. Hard honesty guardrail held:
every AI claim describes what is deployed today (Co-Intelligence in production, grounded on
the Nation's own records, Canadian-controlled infrastructure); no "cutting-edge", no
model/benchmark talk, no em dashes. Contact, defence, how-it-works, stats, proof band, and
doors untouched by design.

## Revisions

### Home (published r24 -> r28)

| Rev | Field | Before | After |
|---|---|---|---|
| r25 | hero h1 | Sourcing, sovereign technology, and protection. | Sourcing, sovereign AI, and protection. |
| r26 | title + meta | "…sovereign technology…" in both | same phrase swap to "…sovereign AI…", nothing else |
| r27 | technology lane body | "Sovereign, Nation-owned software: websites and member portals, a secure workspace, and department tools, hosted in Canada and governed by the Nation. Data that stays yours." | "Nation-owned software with AI at its core: Co-Intelligence, an assistant grounded on the Nation's own files, inside a workspace hosted in Canada and governed by the Nation." |
| r28 | new sovereign-AI band after the proof band | (none) | faraday_feature reuse: eyebrow "Sovereign AI", h2 "AI that lives where your data lives.", body "Co-Intelligence is the assistant inside Anokii, grounded on your own records and running on Canadian-controlled infrastructure. Answers come from your files, and your files go nowhere.", panel "Grounded on your files", CTA See the platform -> /technology |

No new block type was needed: faraday_feature is fully generic (panel label, eyebrow,
heading, body, CTA). Home now carries two of them (sovereign AI, then the Faraday product).
The block-sequence test and the seed gained the band (fnpi-waaseyaa main `423e61b`; suite
147 tests, 826 assertions; deploy run 27349179850 green).

### Technology (published r5 -> r8)

| Rev | Field | Before | After |
|---|---|---|---|
| r6 | hero oneline | "…governed by Council, with AI built in." | "…governed by Council, with AI grounded on the Nation's own records." |
| r7 | module grid order | Website, Member Portal, Drive, **Co-Intelligence**, … | **Co-Intelligence**, Website, Member Portal, Drive, … (bodies unchanged) |
| r8 | hosting block (text_center, beside the closing CTA) | ends "…as sovereign as the software running on it." | appends "Procurement is how you pay for it: the federal 5% set-aside and ISC funding streams, often with little or nothing from band funds." (claims already live on /how-it-works) |

## Verification (cold)

- "Sourcing, sovereign AI, and protection" serving in the h1, title, and meta; the AI band
  heading and body live; "AI" appears 5 times in home's visible text (>= 3 required).
- Co-Intelligence first in the tech grid; new oneline and the funding sentence serving.
- Zero banned words ("cutting-edge"/"state-of-the-art"/"benchmark"), zero em dashes, on
  both changed pages (machine-checked in the revision script and again cold).
- Re-ingest: **136 chunks** from 11 sources (was 135; 2 created, 134 updated, 1 deleted).
  The assistant's index carries the AI band ("AI that lives where your data lives." chunk
  under source `/`).
- Screenshots (all looked at), `storage/framework/_shots/ai-elevation/`:
  `home-prod-1280.png` (hero + AI band in position after the proof band),
  `tech-prod-1280.png` (Co-Intelligence first, funding line in the closing block),
  `home-375.png`, `tech-375.png` (exact-width renders, prod-synced content: everything
  stacks cleanly).

## Matt flag list (addition per the directive)

- **AI-to-the-core positioning applied on Russell's call 2026-06-11, ahead of pillar
  edits; the architecture and spine pillars should gain a line when Matt reviews.** (The
  spine pillar still reads "sourcing, sovereign technology, and protection"; the live hero
  now says "sovereign AI". Pillars were untouched per standing rules.)
- Prior flags carry forward: values one-liners page-side, pillar commas, tagline, "10+
  years", defence stage labels, INDGEN approval, contact-page intro/placeholder doubling.
