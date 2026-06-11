# Editorial pass: copy quality only (2026-06-11)

Follow-up to [2026-06-10-coherence-pass.md](2026-06-10-coherence-pass.md). Principle
(Russell's correction): pillars bind substance and facts; pages carry web copy. No pillar was
touched; no structural change; no new claims.

## Published pointers (rollback reference)

| Page | Old published | New published | Changes |
|---|---|---|---|
| `/` | r15 | **r21** | r16 values one-liners; r17 sourcing lane; r18 protection lane; r19 Pathways lane; r20 proof band body; r21 Faraday band body |
| `/technology` | r3 | **r4** | department modules intro tightened |
| `/how-it-works` | r3 | r3 (unchanged) | see "Item 7" below |
| `/contact` | r2 | **r3** | title matches the h1 ("Contact: Tell us what you need · FNPI") |
| `/defence` | r4 | **r5** | capability subline removed (operator sentence now appears once, in the intro) |

Home rendered word count: **593 before, 471 after** (full page text including nav and
footer), a 21% trim with no claim lost: the moat sentence still leads the proof-band header,
the Faraday detail consolidated into the Faraday band, the full values text lives in the
pillar.

## Item 7 finding: the double space does not exist

The how-it-works hero h1 reads `Start where it shows. Grow as you trust it.` with a single
space, in both the prod entity data and the rendered HTML, and a sweep of every text field
on all five pages found zero double spaces anywhere. Whatever showed a double space was a
rendering artifact on the viewing side. No revision was stacked; /how-it-works stays at r3.

## One line of template work (flagged, since "none expected")

`module_grid` rendered its subline unconditionally, so removing the defence subline would
have left an empty `<p class="sec-sub">` in the band. The template now omits an absent or
empty subline (one conditional, covered by a new test asserting no empty paragraph).
fnpi-waaseyaa main `121b505` (merge of editorial-pass, commit `d6ee6c8`); infra `60abaf8`
(FNPI_REF bump); deploy run 27344429169 green in 2m25s; suite 138 tests, 762 assertions.

## Verification (cold client, ~2026-06-11 16:30Z)

- All five pages 200. Every new string probed and found exactly once: the four values
  one-liners as four discrete `<p>` items, the three tightened lane bodies, the proof band
  body ("We deliver sourcing, sovereign technology, and protection through one company…"),
  the Faraday band body ("Signal-blocking enclosures… In stock and available on request."),
  the technology department intro, the contact title.
- "The qualification is the moat" gone from the proof-band body (header unchanged:
  "Procurement is our qualification. Sovereignty is our purpose.").
- Defence: "we build it, run it, or supply it ourselves" appears exactly once (the
  who-we-are intro); the capability band goes straight from heading to grid with no empty
  paragraph. EOI claims untouched.
- Zero em dashes, zero `_oj`/Niiwin/Maamawichigewin, zero Waaseyaa, zero INGEN across all
  five rendered pages.
- `app:ingest-knowledge`: 135 chunks from 11 sources (0 created, 135 updated, 0 deleted).
- Cache purge: still "not configured" (the Cloudflare token has not landed in fnpi.env yet;
  the dashboard purge + token ask from the coherence-pass report stands). Edge remains
  uncached (`Cf-Cache-Status: DYNAMIC`), so the new copy serves cold regardless.
- Screenshots (desktop 1440 + mobile 390, all five pages):
  `storage/framework/_shots/editorial/`.

## Matt flag list (carried forward)

1. **NEW: the values one-liners are page-side condensations of his full pillar text.** The
   pillar keeps the four full paragraphs verbatim; if Matt prefers the full versions on the
   page, they are one revision away (home r15's values list is byte-identical to the pillar
   sentences and can be re-published or re-stacked).
2. Pillar comma typo ("Inc., was founded… goods, and services") — his one-line edit; the
   page already renders the comma-free version.
3. Tagline pillar still empty (Decide open); the home hero carries the spine line as the
   placeholder.
4. "10+ Years of supply-chain experience" beside "2017 Operating and delivering since" in
   the proof band; only backable by pre-incorporation experience.
5. Defence stage-label posture: capability grid carries no stage labels (voice pillar wants
   them; the EOI posture argues against). "Direct access" in the materiel module mildly
   outruns "partner-network access". Both deliberately unresolved.
6. INDGEN spelling live per architecture r3; Matt's approval of Russell's correction still
   outstanding. Voice pillar still status=draft with its Decide open.
7. Cloudflare purge automation dormant pending Russell's token (Zone > Cache Purge,
   fnprocure.ca only) + Zone ID in `compose/fnpi/secrets/fnpi.env`.
