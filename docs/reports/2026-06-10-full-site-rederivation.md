# Full-site re-derivation and production publish, 2026-06-10

Re-derived all five public pages of fnprocure.ca from the identity pillars as they stood on
prod after Matthew's refinement session, and published them. The draft sign-off gate was
dropped for this batch by Russell's directive; Matt reviews live, revision history is the
rollback net. Pillar entities were read-only throughout this run; no pillar was edited and no
Decide chip was cleared.

## Pillar snapshot (the binding derivation source)

- Snapshot taken read-only from the prod sqlite inside fnpi-app: **2026-06-11T02:42:02Z**
  (evening of 2026-06-10 local). Artifacts (gitignored, on the workstation):
  - `storage/framework/_prod_snapshot.json` (raw table dump, opened SQLITE_OPEN_READONLY)
  - `storage/framework/_pillars_current.json` (current pillar state, all fields)
  - `storage/framework/_pillars_history.md` (all 67 pillar revisions with logs)
  - `storage/framework/_reconcile/*.json` (every page revision, published and draft)
- Latest pillar revisions at snapshot: purpose r34, values r6, vision r3, mission r2, spine r2,
  architecture r3, moat r2 (+ Anishinaabemowin peer row r1), aud-nations r2, aud-buyers r1,
  aud-funders r1, aud-fnbiz r2, voice r2, tagline r1, logo r2, paletteType r1, imagery r1,
  culture r1, proof r1.
- Matthew's session is the block of edits 2026-06-08 21:58Z through 2026-06-09 00:27Z
  (~3.5 hours, matching the "3-hour refinement pass"). The only later pillar edits are
  Russell's: architecture r3 (INGEN→INDGEN, 2026-06-10) and the moat oj translation row.
- Note on the handoff's assumption: the 7 pending drafts (home r2–r6, technology r2,
  how-it-works r2) were stacked 2026-06-10 ~14:00Z, AFTER Matt's session, and already carried
  most of its outcomes. Reconciliation therefore mostly confirmed the drafts; the genuinely
  new derivations are home r7–r10 below.

## The five open identity calls

1. **Tagline** — still empty at r1, Decide chip open ("pick the primary"). The hero keeps the
   moat-derived line ("Four layers. Only FNPI has all four.") as the placeholder. LEFT OPEN.
2. **Vision wording** — resolved by Matt at r3: "…strengthen national sovereignty while
   fostering self-governance". Live in home's About block.
3. **INDGEN** — architecture r3 (current, binding) spells INDGEN. No public page mentions the
   product, so nothing changed on pages. NOTE: r3's revision log says "Matt to approve: his
   content" — the spelling is live-binding but Matt's explicit approval is still outstanding.
4. **Lane4/Pathways** — resolved by Matt at architecture r2 ("Pathways named as fourth lane,
   procurement enablement, public name"). Home lane4 and the how-it-works Pathways naming match.
5. **Architecture Decide chip** — cleared by Matt at r2 (decide_label and decision both empty
   at the current revision). Resolved; nothing to apply.

## Per-page outcome and published pointers (rollback reference)

| Page | Old published | New published | What changed |
|---|---|---|---|
| `/` | r1 | **r10** | r2–r6 (prior drafts, confirmed against pillars) + r7–r10 below |
| `/technology` | r1 | **r2** | existing draft confirmed: Waaseyaa engine name removed from public copy |
| `/how-it-works` | r1 | **r2** | existing draft confirmed: Pathways named as the procurement enablement lane |
| `/contact` | r1 | r1 (unchanged) | nothing to re-derive; draft = published = r1 |
| `/defence` | — (new) | **r1** | created+published by app:seed-pages during the deploy |

Rollback: `PagesService::rollbackPublished(id, rev)` (or repository `setPublishedRevision`)
per page; old pointers above. The deploy also left a pre-deploy DB backup on the Pi
(`waaseyaa.sqlite.pretx-<ts>.bak` in the fnpi_storage volume, taken by deploy-fnpi.yml).

### New home revisions stacked this run (one change per revision)

- **r7** — "Our Purpose" added as the first About item, verbatim from the purpose pillar's
  canonical statement (locked by Matt at r33/r34).
- **r8** — Values item aligned verbatim to the values pillar (full sentences, no re-stitching;
  the prior draft had dropped clauses and merged sentences).
- **r9** — Proof band aligned to the positioning spine: "We turn Indigenous procurement
  qualification into a platform…" (the prior draft said the *purpose* became the platform; the
  spine says the *qualification* does). "FNPI" → "We" is the only adaptation.
- **r10** — Privacy & Protection lane became a link card to `/defence` with the
  "Defence & security →" arrow (the directive's lane3 link).

### /defence reconciliation

Claims verified one-by-one against the filed DAF EOI: operator standing, OCAP-designed
platform, no facial recognition by design, partner-network materiel access, both-sides-of-the-
chain systems view. All present, none strengthened (independently re-verified by an
adversarial review pass; see Flags for the two watch items). Tone matches the voice pillar:
plain, no em dashes, no fear-selling. Proof statements (Sagamok Anishnawbek, since 2017, ISC
Business Directory, CCAB certified) each match a proof-pillar pill. The structural strings I
authored around Russell's copy (sec_h labels, two-button CTAs, checklist bold leads) checked
against the pillars; the two-button CTAs exist because the shared hero/hero_cta templates
render both buttons unconditionally.

## Code shipped (fnpi-waaseyaa main)

- `c5a59c2` feat(pages): add the public /defence page, pinned to the filed DAF EOI
- `1a99612` merge of defence-page → main (the deployed ref)

Beyond the page itself (seed entry, route, controller method, structure test), the
verification pass forced three fixes that shipped in the same commit:

1. **IngestKnowledgeCommand::PAGES now includes /defence** — without it the new page would
   never have entered the Co-Intelligence index and the chunk-count check would have passed
   silently.
2. **/technology seed copy no longer names the Waaseyaa engine** — prod was safe (the seeder
   skips existing paths) but any fresh environment would have published the internal name.
3. **Knowledge corpus posture amended (dated, history preserved)** — fnpi-narrative.md and
   fnpi-master-plan.md said the defence lane "stays low-key and off public materials"
   (Matt, 2026-06-02). Left unamended, the assistant's grounding corpus would contradict the
   live site. The amendment records: defence public via /defence restricted to the filed EOI
   claims; drones and military relationships remain private.

Deploy: FNPI_REF bumped `7cacffe` → `1a99612` (waaseyaa-infra `bd193cf`); deploy-fnpi run
27321077359-adjacent (id 27321077313) green in 2m28s including the 4-page + Anokii smoke check.

## Ingest result

`app:ingest-knowledge` on prod: **135 chunks from 11 sources** (was 134; 11 created,
124 updated, 10 deleted). `/defence` contributes 6 chunks. Probes confirmed the index carries
the new copy (operator standing, no-facial-recognition, four lanes, purpose statement, spine
clause, amended posture) and that "Waaseyaa" appears only in internal sources
(/anokii/identity pillars + knowledge docs), never in public page chunks. No em dashes in the
corpus.

## Live verification (HTTP, 2026-06-11 ~03:30Z)

- All five pages 200; titles correct (home title is the spine-derived one from r2, not the
  legacy "Service & Sourcing Solutions" which exists only at r1).
- New copy serving: every probe found exactly once (hero, Our Purpose, purpose statement,
  spine clause, full Inclusion sentence, lane3 href+arrow, engine-free technology copy,
  Pathways naming, defence operator hero).
- Zero `_oj` leakage (no `_oj`, "Niiwin", "Maamawichigewin" in any rendered page; the hero
  template renders no `*_oj` field — the bilingual line in the home data is inert).
- Zero em dashes, zero "Waaseyaa", zero "INGEN" across all five rendered pages.
- Honest stage labels intact: Running today / Building ×2 / Shipping now on home;
  "what we are building now" on /technology.

Screenshots (workstation, gitignored): `storage/framework/_shots/{home,technology,
how-it-works,contact,defence}-{desktop,mobile}.png` (desktop 1440px, mobile 390px), plus
`home-mobile-check.png` (device-emulated). Desktop and mobile render correctly; see flag on
the pre-existing mobile hero overflow.

## Flags

**Defence / EOI watch items (copy shipped as authored; for Matt's live review):**
- The capability grid carries no stage labels; all four areas read as current capability. The
  voice pillar mandates stage labels and the internal honesty line pegs the security lane at
  "Growing". I did not add labels because the copy is EOI-pinned and staging it could
  contradict the filed EOI's capability claims — but the tension between the voice pillar and
  the EOI posture is real and is Matt's call.
- The materiel module says "Through our partner network, **direct** access to body armour…".
  "direct" mildly strengthens "partner-network access". Russell's wording, shipped verbatim;
  consider dropping "direct" if the EOI reading should be exact.

**Pillar content with no home on the five pages (no new pages were invented):**
- tagline (empty body; 5 candidates in pills + ~10 more in notes) — also the open Decide.
- aud-buyers message ("Meet your Indigenous procurement commitments with real capability, not
  a pass-through.") — no buyer-facing surface exists.
- aud-fnbiz r2 message ("Your Nation has always governed its own future. Now govern your data
  the same way.") — Matt replaced the vendor-onboarding message with this, but no page carries
  it; home lane4 carries the Pathways service description (derived from architecture r2), which
  is adjacent but not this message.
- INDGEN (public name per architecture r3) — no surface; the companies-facing product page
  does not exist yet.
- culture pillar (Anishinaabe foundation, name meanings) — implicit only.
- proof pills "FNPA (early registrant)" and "Reference build (live)" — on no page.
- Notes-level drafted copy with no surface: the moat pillar's notes hold a full funder-facing
  document (Strategic Priorities / Investment Impact); aud-funders notes hold the 5,000-business
  pitch; logo notes hold the wolf-totem meaning.

**Identity calls Matt left open:** tagline (above); INDGEN spelling live but Matt's approval
of Russell's r3 correction outstanding; voice pillar still status=draft with its Decide open
("formalize a short voice guide").

**Wording shipped verbatim that Matt may want to touch (pillars were binding):**
- The canonical purpose statement contains "First Nations Procurement Inc.**,** was founded…
  goods**,** and services" — the stray commas are now live on the homepage. Fix the pillar,
  then republish home (one saveDraft + publish).
- The vision body is lowercase "indigenous" in the pillar; the page sentence-cases it
  ("Indigenous"), the only deviation beyond casing being none.

**Pre-existing / cosmetic (not introduced by this run):**
- Mobile (~390px): the hero's two side-by-side CTA buttons exceed the viewport's min-content
  width, causing slight horizontal overflow. Present in the r1 site; CSS fix is out of scope
  for a content publish.
- Home About block renders 3+1 on desktop (Purpose/Vision/Mission + Values below) since
  .vm-wrap caps at 3 columns; Values is the longest item, so it reads acceptably.
- The home `h1_oj` field carries only the first sentence of the moat's oj translation and is
  not rendered by any template (inert by design; BlockTemplatesTest pins that it never reaches
  public HTML).
- The `/` seed entry in PageSeedData is still the original r1 copy (three lanes, old vision).
  Harmless on prod (seeder skips existing paths) but a fresh environment would seed stale copy;
  worth a follow-up sync of seeds to published content.

## Stat watch (kept from published copy under the pillar-silent rule)

- "10+ Years of supply-chain experience" sits next to "2017 Operating and delivering since" in
  the home proof band. Not contradicted by any pillar, but only backable by pre-incorporation
  experience; confirm with Matt.
