# CTA language sweep + two-door closing band (2026-06-11)

Follow-up to [2026-06-11-about-block-layout.md](2026-06-11-about-block-layout.md). Two
changes in one run: every "quote"/"free" framing replaced site-wide (FNPI sells
consultative engagements; the paid deliverable is a sovereignty assessment), and the home
closing band rebuilt as a true two-door layout. Same playbook as the About fix: structured
data over text parsing, general-case guards, round-trip verification.

## Part 1: the full replacement list

Template surfaces (fnpi-waaseyaa main `2bfdcb8`, merge of cta-language-and-doors):

| Surface | Before | After |
|---|---|---|
| Header button, all pages (desktop) | Get a free quote | Book an assessment |
| Mobile menu panel CTA | Get a free quote | Book an assessment |
| Contact form submit | Request a quote | Send |
| Contact form message placeholder | "…and we'll come back with a quote." | "…and we'll come back with next steps." (sweep catch, same register) |

Page data (content revisions, one change per revision):

| Page | Revision | Field | Before -> After |
|---|---|---|---|
| `/` | r23 | hero primary CTA | Get a free quote -> Book an assessment |
| `/` | r24 | closing band door 1 CTA | Request a quote -> Book an assessment (within the doors reshape) |
| `/technology` | r5 | hero + closing CTAs (both) | Request a quote -> Book an assessment |
| `/how-it-works` | r4 | closing CTA | Request a quote -> Book an assessment (sweep catch; the directive named only /technology, the sweep found this one too) |
| `/contact` | r4 | meta description | "Request a quote or get started." -> "Book an assessment, or tell us what you need." (rest unchanged) |

Kept deliberately: home Faraday band "Request Faraday cases" (no banned words; the Faraday
commerce run supersedes it later). Defence page was already clean. Housekeeping: the
`contact_page_has_quote_form_to_mailto` test renamed to `…has_contact_form…`, and the
pricing-guard comment now says "behind the assessment CTA".

**Zero-occurrence check (cold, all five pages):** case-insensitive grep for "quote" and
"free" over the full rendered HTML (not just visible text) finds nothing. "Book an
assessment" appears 4x on home and /technology, 3x on /how-it-works and /contact, 2x on
/defence (header + menu panel), exactly as expected.

## Part 2: the two-door closing band

`cta_band_center` accepts a structured `doors` list. With doors: the centred eyebrow and
h2 stay ("Get in touch" / "Tell us what you need."), and below them two equal white door
panels (the lane-card house pattern) in a 2-column grid, text left-aligned:

- **For Nations** — "From a single sourcing request to a Nation-wide platform you own."
  -> Book an assessment (/contact, cyan primary)
- **For governments & industry** — "Sourcing, defence and security, and protection
  through Indigenous procurement channels." -> Defence & Security (/defence, ink button)

Tops align (grid stretch), buttons bottom-align regardless of text length (flex column,
`margin-top:auto`). Doors stack to one column at the existing 880px breakpoint; the <=520px
type steps apply unchanged. Without `doors` the block renders the original sec_sub +
two-button contract byte-for-byte, pinned by a guard test next to the doors test
(suite: 147 tests, 823 assertions).

The home data reshape (r24) replaced sec_sub/cta_primary/cta_secondary with the two door
entries, guarded on the exact prior strings and round-trip-verified against the directive
strings. The door copy is the same two sentences the old paragraph carried, now split per
door with sentence-initial casing and "&" in the second label, per the directive.

## Shipped + published

- Code: fnpi-waaseyaa `2bfdcb8`; infra `5887e54` (FNPI_REF bump); deploy run 27348070752
  green.
- Published pointers: `/` r22 -> **r24**, `/technology` r4 -> **r5**, `/how-it-works`
  r3 -> **r4**, `/contact` r3 -> **r4**, `/defence` unchanged at r5.
- Re-ingest (text changed): 135 chunks from 11 sources (0 created, 135 updated).
- Cache purge: still "not configured" (Russell's token outstanding); edge remains
  uncached so the new copy serves cold.

## Verification evidence

- Cold zero-occurrence sweep: above.
- Doors live: two `.door` panels with both labels on the cold-fetched home.
- Contact form: "Send" submit and the next-steps placeholder serving; new meta description
  serving.
- Screenshots (all looked at), `storage/framework/_shots/cta-doors/`:
  - `home-prod-1280.png` (header CTA, hero CTA, two doors side by side),
    `home-prod-768.png` (header + burger; band below the crop, covered by the local 768)
  - `home-1280.png`, `home-768.png`, `home-375.png` — exact-width renders of the same
    merged commit with prod-synced content: doors 2-up at 1280, stacked at 768 and 375,
    buttons bottom-aligned, header/menu CTAs reading Book an assessment.

## Carried forward

Matt flag list unchanged. Cloudflare purge token still pending (chips: seed-sync still
parked too — the committed seeds now also lag the CTA sweep, one more reason to run it).
