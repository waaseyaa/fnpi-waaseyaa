# About block layout fix (2026-06-11)

Follow-up to [2026-06-11-mobile-responsive-pass.md](2026-06-11-mobile-responsive-pass.md).
Fixes the home "A trusted Indigenous partner" block: Our Values orphaned beside two empty
grid cells, centre-aligned multi-line body text, and the four value one-liners running
together as one visual paragraph. Copy untouched, verified byte-identical.

## Implementation: data reshape, not Twig parsing

The values entry was reshaped from four "Label: clause" strings into structured
`{lbl, text}` entries (one content revision), and the `vision_mission` template partitions
its items by body shape:

- string or list-of-strings body -> a column card in the grid, exactly as before;
- list of `{lbl, text}` entries -> the item leaves the grid and renders as a full-width
  band below it.

Parsing "Label: clause" text blobs in Twig was rejected: the block system's contract is
structured fields in, markup out; a colon-splitting filter would break the first time a
clause contains a colon. The reshape script guards on the exact current strings, splits on
the first ": ", and round-trip-verifies that `lbl + ': ' + text` reproduces every original
line byte-for-byte before saving.

Layout (template + CSS in templates/base.html.twig):
- Row 1: Our Purpose / Our Vision / Our Mission as an explicit three-column grid
  (`repeat(3,1fr)`, no more auto-fit, so no orphan at any width), text left-aligned, cyan
  eyebrow labels kept (also left-aligned with their columns).
- Row 2: the values band, separated by a hairline top border: four items in a row, each a
  bold ink lead ("Inclusion:") plus its clause in muted text, the checklist's house
  pattern.
- Responsive: row 1 stacks to one column at the existing 880px breakpoint; the values go
  4-up desktop, 2x2 at <=880, single column at <=520 (the responsive pass's phone step).
- General case pinned by tests: a block with 2-3 plain items renders today's markup with
  no band (`vision_mission_without_structured_values_renders_no_band`), and the structured
  shape produces the band with "Label: clause" text intact
  (`vision_mission_renders_structured_values_as_a_full_width_band`).
  Suite: 145 tests, 813 assertions.

## Shipped

- fnpi-waaseyaa main `82a5f53` (merge of about-block-layout, commit `add5d0a`); infra
  `dec82fb` (FNPI_REF bump); deploy run 27347070733 green.
- Home published **r21 -> r22** (the one data-reshape revision, log: "Layout: values entry
  reshaped into structured label+clause items for the full-width values band. Rendered
  words unchanged."). No other page changed.

## Verification

- **Rendered words byte-identical:** the About section's text content, fetched cold from
  prod before the change and after publishing r22, is IDENTICAL (whitespace-normalized
  string equality; baseline kept at `storage/framework/_about_text_before.txt`).
- Live markup probes: `vm--values` band present with four `vm-value` items and bold leads.
- Screenshots (all looked at), `storage/framework/_shots/about/`:
  - `about-prod-1280.png`, `about-prod-768.png` — prod cold: three parallel left columns +
    four-up values band at 1280; single-column stack + 2x2 values at 768.
  - `about-1280.png`, `about-768.png`, `about-375.png` — exact-width local renders of the
    same merged commit with page tables synced from prod (375 via the same-origin iframe
    harness, since raw headless cannot render below ~500px and prod sends
    X-Frame-Options): no orphan, left-aligned columns, four distinct labelled values at
    every width; single column on the phone width.

## Carried forward

Matt flag list unchanged (values one-liners page-side, pillar commas, tagline, "10+
years", defence stage labels, INDGEN approval). Cloudflare purge automation still dormant
pending Russell's token; `app:purge-cache` reported "not configured" again on this
publish.
