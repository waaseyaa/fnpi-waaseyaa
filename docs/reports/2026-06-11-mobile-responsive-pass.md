# Mobile navigation + responsive pass (2026-06-11)

Follow-up to [2026-06-11-editorial-pass.md](2026-06-11-editorial-pass.md). Code-only
(base template + block template + tests); content untouched, so no re-ingest was needed
(verified: the only template change affecting rendered text is the new menu markup, which
carries no copy beyond the nav labels already present).

Shipped: fnpi-waaseyaa main `6bb7146` (merge of mobile-responsive, commit `6595812`);
infra `eec3147` (FNPI_REF bump); deploy run 27345595619 green. Suite: 143 tests,
802 assertions.

## Root cause of the "horizontal overflow": a screenshot-tooling artifact

The previously flagged "pages render wider than a 390px viewport" was not a layout bug.
Chromium's new headless mode enforces a minimum window width of about 500px:
`--window-size=390` produces a **500px layout cropped to a 390px image**, which is exactly
what every earlier "mobile" screenshot showed (the clipped header button and oneline).
Proof: a `--window-size=500` shot of the same page is pixel-identical to the cropped 390
one (`storage/framework/_shots/_overflow_check.png` vs `_overflow_check500.png`). The
`--force-device-scale-factor=2` variant was differently wrong (halves the CSS viewport).

With real emulated viewports (preview browser + same-origin iframe harness), in-browser
measurement found **zero horizontal overflow on any of the five pages at 320 / 375 / 390 /
768 / 1024 / 1280, menu closed or open** — `document.scrollWidth === viewport` everywhere,
30 page-width combos. Real phones were never affected; nothing was papered over with
`overflow-x:hidden` because there was nothing to hide. (The only element extending past the
viewport is the hero's decorative `.glow`, clipped by the hero's existing
`overflow:hidden`.)

## The real bug: no mobile menu (fixed)

Below 880px the nav links were `display:none` with no replacement, so Technology, Defence &
Security, and Contact were unreachable from a phone. Now:

- A hamburger toggle in the header: a real `<button>` (44x44px), `aria-expanded`,
  `aria-label` (Open menu / Close menu), `aria-controls="site-nav"`. Escape closes the menu
  and returns focus to the button; choosing a link closes it. Vanilla inline script
  (~20 lines), no dependencies. The bars animate to an X via the `aria-expanded` state.
- The panel drops from the sticky header in the house dark theme (#0a0e12, cyan hover),
  with the three links (52px tap targets) plus a full-width cyan "Get a free quote" CTA.
- Desktop (>880px) is unchanged; the footer nav is unchanged.
- At <=520px the header's quote button hides (the burger always fits at 320; the CTA lives
  in the panel and in every hero).

## Responsive fixes from the audit

- **Forms (iOS focus-zoom):** inputs and textarea 14px -> 16px; a `.contact .field select`
  16px override outranks the page-level head_styles rule (the page CSS renders after the
  base stylesheet, so it needed the extra specificity). Note for the seed-sync follow-up:
  the contact page's own head_styles still says 14px for the select; the base override
  wins, but the page CSS should be updated when seeds are synced.
- **Phone type steps (<=520px):** hero h1 38px (from 48px at <=880), `.sec-t` 32px,
  `.band h2` 28px, closing-band h2 32px. Headings wrap in 2-4 natural lines at 320-390;
  no clipping, no one-word-per-line.
- **hero_cta h2** font-size moved from an inline style into `.hero-cta-h2` so it can
  respond (inline styles cannot be overridden by media queries).
- Everything else already stacked correctly (measured + eyeballed): lane cards, stats band
  (2-up), module grids (2-up at phone widths, readable), checklists, stage ladder (2x2),
  migration list, two-door band, values items, footer.

Pinned by a structure test: every page's rendered header carries the three links, the
panel CTA, and the accessible toggle (`header_nav_carries_the_links_and_the_mobile_menu_toggle`).

## Verification

Cold prod probes: toggle, `site-nav`, panel CTA, and `aria-controls` present on all five
pages; 16px input CSS and the select override serving; `hero-cta-h2` class live.

Screenshot matrix (all eyeballed, not just taken), under
`storage/framework/_shots/responsive/`:

- **Prod, raw headless** (>=500px is faithful): all five pages at 500, 768, 1280.
- **True 375/320 viewports** via a same-origin iframe harness against the local server
  running the **same merged commit with the page tables synced from prod** (raw headless
  cannot render below ~500px, and prod sends `X-Frame-Options: SAMEORIGIN` so the harness
  cannot frame prod directly): all five pages at 375, home + contact at 320, and the menu
  **open** state on home and /defence at 375. The preview browser additionally verified the
  open/close/Escape/focus behaviour interactively at 375, 390, and 768.

The earlier `_shots/` and `_shots/coherence/`, `_shots/editorial/` sets predate this pass
and carry the 500px-crop artifact in their `*-mobile.png` files; the `responsive/` set
supersedes them for anything mobile.

## Flags

- **Needs a content decision, not CSS:** none found. The longest headings ("SOURCING,
  SOVEREIGN TECHNOLOGY, AND PROTECTION.", "AN OPERATOR'S STANDING, NOT AN OBSERVER'S.")
  wrap acceptably at 320px with the 38px step; no heading needs rewording for phones.
- `X-Frame-Options: SAMEORIGIN` is being served for fnprocure.ca (likely Cloudflare or
  Caddy; the framework's SecurityHeadersMiddleware is documented as unwired). Harmless
  for the site; worth knowing if anything ever needs to embed it.
- Matt flag list unchanged from the editorial-pass report (values one-liners, pillar
  commas, tagline, "10+ years", defence stage labels, INDGEN approval, Cloudflare purge
  token still pending).
