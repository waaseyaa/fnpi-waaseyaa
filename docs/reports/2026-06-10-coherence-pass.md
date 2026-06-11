# Coherence pass: nav, two-door home, single-door defence, cache purge plumbing (2026-06-10)

Follow-up to [2026-06-10-full-site-rederivation.md](2026-06-10-full-site-rederivation.md).
All decisions Russell's; same rules: pillars read-only, no em dashes, honest stage labels,
defence claims pinned to the filed DAF EOI.

## Published pointers (rollback reference)

| Page | Old published | New published | Changes |
|---|---|---|---|
| `/` | r10 | **r15** | r11 hero spine line; r12 lane3 plain card; r13 two-door closing band; r14 purpose comma fix (page only); r15 values as four discrete items |
| `/technology` | r2 | **r3** | Anokii sentence: records at the centre, AI from day one |
| `/how-it-works` | r2 | **r3** | AI Operator block ends at "non-extractive by design." |
| `/contact` | r1 | **r2** | h1 "Tell us what you need." + every-door intro |
| `/defence` | r1 | **r4** | r2 hero secondary CTA removed; r3 closing secondary removed; r4 Faraday line in the materiel module |

Rollback per page via `PagesService::rollbackPublished(id, rev)` to the old pointer above.

## The cache situation (URGENT item): what I found, did, and could not do

**Finding first:** at verification time the edge was NOT serving stale HTML. Apex, www,
http, and browser-UA requests all returned the current published copy with
`Cf-Cache-Status: DYNAMIC` (Cloudflare does not edge-cache this zone's HTML; no cache rule
matches) and the origin sends `Cache-Control: no-store, no-cache`. The stale view Russell
saw on the bare URL was therefore almost certainly his browser's cache of an earlier visit
(a cache-busted URL bypasses the browser cache, which matches the symptom exactly).

**What I could not do:** purge the Cloudflare cache via API. No Cloudflare API token exists
anywhere in this infrastructure — the ansible vault holds only the tunnel token and the
Anthropic key, and no env file on the Pi or workstation carries CF credentials. The
Cloudflare account is dashboard-managed by Russell.

**WHAT RUSSELL NEEDS TO DO (one-time):**
1. Belt-and-braces now: Cloudflare dashboard > fnprocure.ca > Caching > Configuration >
   **Purge Everything** (and hard-refresh the browser that showed the stale page).
2. To make purging automatic: create an API token (dashboard > My Profile > API Tokens),
   scope **Zone > Cache Purge** for the **fnprocure.ca zone only**, note the **Zone ID**
   (zone Overview page, right column), and add both to the Pi's
   `compose/fnpi/secrets/fnpi.env` (mode 0600, same delivery as the other secrets):
   `CLOUDFLARE_PURGE_TOKEN=...` and `CLOUDFLARE_ZONE_ID=...`, then
   `docker compose up -d --force-recreate fnpi-app` (or just wait for the next deploy).
   Never put the token in chat, the repo, or GitHub secrets; it lives server-side only.

**What is automatic from now on (once the token lands):**
- Every workspace **publish and rollback** purges the edge cache
  (`CloudflareCachePurger` wired into `PagesService`, fail-soft: a missing token or API
  error never blocks a publish).
- Every **deploy** runs `app:purge-cache` after the container swap (deploy-fnpi.yml).
- Manual retry any time: `docker exec -w /app fnpi-app vendor/bin/waaseyaa app:purge-cache`.
Until the token lands, both paths print a clear "purge not configured" notice and the purge
remains the manual dashboard step above.

## Code shipped

fnpi-waaseyaa main `ceddea6` (merge of coherence-pass, commit `55964a3`); infra `4270d87`
(FNPI_REF bump + deploy purge step, one commit); deploy run 27322044092 green in 2m0s.

- Top nav and footer: Technology / **Defence & Security** / Contact. How it works left both
  navs; the page stays live (200) and is reached via the two "See how it works" buttons on
  /technology. No redirect.
- Contact form dropdown gained "Defence & security".
- `hero` and `hero_cta` templates render the secondary button only when present (no empty
  ghost button), enabling the defence page's single-door CTAs.
- `vision_mission` accepts a list-valued body rendered as discrete paragraphs; string
  bodies unchanged. The four values' wording is byte-identical to r8, restructured only.
- The `no_defense_or_drones_anywhere_public` guardrail now encodes the 2026-06-10 posture:
  "defence" is no longer banned site-wide (the nav carries it by design), while **drone,
  military, weapon, ISR, autonomous monitoring stay banned on every public page including
  /defence**, which now joins all three guardrail sweeps (defence-words, pricing, dashes).
  Suite: 137 tests, 761 assertions, green.

## Verification (cold client, no cache-buster, ~04:30Z)

- All five pages 200; bare `/` serves the new hero ("Sourcing, sovereign technology, and
  protection.") with the spine-derived oneline.
- Nav shows Defence & Security (top + footer) on all five pages; how-it-works absent from
  every nav, present only as /technology's two CTA buttons; /how-it-works itself 200.
- Home: lane3 is a plain card again (no link, no arrow); closing band is the two-door
  ("Request a quote" -> /contact, "Defence & Security" -> /defence); purpose statement
  reads without the stray commas; the four values render as four discrete paragraphs.
- Defence: zero "See the platform" links; the only doors are Contact (hero "Start the
  conversation", closing "Contact us", nav quote button); no ghost buttons; the Faraday
  line renders in Personnel protection & materiel.
- Technology: "Anokii puts the Nation's records at the centre, with AI built in from day
  one." How-it-works: the AI Operator block ends at "non-extractive by design."
- Contact: new h1 + intro; dropdown has the defence option; funders line intact.
- Zero em dashes, zero `_oj`/Niiwin/Maamawichigewin, zero Waaseyaa, zero INGEN across all
  five rendered pages.
- `app:ingest-knowledge`: 135 chunks from 11 sources (3 created, 132 updated, 3 deleted —
  the changed copy resynced; /defence still 6 chunks).
- Screenshots (desktop 1440 + mobile 390, all five pages):
  `storage/framework/_shots/coherence/`. The pre-pass set remains in
  `storage/framework/_shots/`.

## Matt flag list (carried forward)

1. **Pillar typos are his one-line edit:** the purpose pillar still reads "First Nations
   Procurement Inc.**,** was founded … goods**,** and services". The PAGE now renders the
   comma-free version (home r14); the pillar is untouched. When Matt fixes the pillar the
   page already matches.
2. **Tagline pillar still empty** (Decide open). The home hero now carries the spine line
   as the placeholder; if Matt picks a tagline, the hero follows the pillar.
3. **"10+ Years of supply-chain experience"** still sits beside "2017 Operating and
   delivering since" in the proof band; only backable by pre-incorporation experience.
4. **Defence stage-label posture:** the capability grid still carries no stage labels
   (voice pillar wants them; the EOI posture argues against). Deliberately unresolved;
   Matt's call. Related: "direct access" in the materiel module mildly outruns
   "partner-network access".
5. INDGEN spelling is live per architecture r3; Matt's approval of Russell's correction is
   still outstanding. Voice pillar remains status=draft with its Decide open.
6. Minor: the contact page <title> still says "Tell us what your Nation needs" while the
   h1 now says "Tell us what you need" (title change was not in the directive; one
   saveDraft away if wanted).
