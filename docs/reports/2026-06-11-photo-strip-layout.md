# photo_strip equal-height row fix (2026-06-11)

Follow-up to [2026-06-11-photo-placement.md](2026-06-11-photo-placement.md). CSS/template/
renderer only; images, captions, alt text, and page content untouched (no revisions; home
stays published at r29).

## The fix

Equal-width columns let each image take its natural height, so the portrait TMX shot
towered over the landscape Xi'an shot and its drifting caption. Now, on desktop (>880px):

- Every image in a row shares **one height**: target 420px, capped at `min(420px, 70vh)`.
- Each width is proportional to its aspect ratio; **no cropping, no object-fit, no
  distortion** (verified live: rendered ratio = intrinsic ratio).
- The row is centred with the house gap; if a combination would overflow the container at
  target height, the whole row scales down **uniformly** (verified at 1024: both images
  shrink together, heights stay equal).
- Captions sit on a single shared baseline directly under the row, aligned with their
  image's left edge (guaranteed structurally: figures are columns and the images above the
  captions are equal-height).
- ≤880px stacked rendering unchanged.

## How (the math, since it has to hold for any 1-3 images of any orientation)

Figures `flex: var(--ar) 1 0%` — with flex-basis 0, widths distribute proportionally to
aspect ratios, which makes heights mathematically equal at ANY container width. Each
figure also carries `max-width: calc(min(420px,70vh) * var(--ar))`; because every cap is
the same target height times that figure's ratio, the caps bind all figures at the same
threshold — the row is either all at target height (centred) or all scaled down together.
There is no mixed regime.

The ratio reaches CSS as the `--ar` custom property: `PublishedPageRenderer` measures each
photo's intrinsic dimensions from the shipped file at render time (computed per render,
never stored, so content is untouched; a missing file means no `--ar` and a graceful
fallback). The same measurement emits `width`/`height` attributes on the `<img>`, which
also removes layout shift on load. One subtlety: the 1px frame moved from `border` to an
inset `outline` — under border-box sizing the border was skewing content height by ~1.3px
between different ratios; with the outline the measured delta is 0.02px.

Pinned by a mixed-orientation template test (portrait + landscape + a dimensionless photo
falling back to `--ar:1`). Suite: 165 tests, 919 assertions, green.

## Shipped + verified

- fnpi-waaseyaa main `f262649` (merge of photo-strip-layout); infra `5ff3a39`; deploy run
  green. No content revisions.
- Live measurement at 1280 (emulated viewport): image heights 420.0 / 420.0 (delta
  0.02px), caption baseline delta 0.02px, captions aligned to image left edges, row
  centred, aspect ratios preserved.
- Screenshots (all looked at), `storage/framework/_shots/photo-strip-layout/`:
  `home-prod-1280.png`, `home-prod-1024.png` (prod cold: equal heights, shared caption
  baseline, no voids, faces uncropped; 1024 shows the uniform scale-down),
  `home-768.png`, `home-375.png` (exact-width renders: the unchanged stacked layout).
