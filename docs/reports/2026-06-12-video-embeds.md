# Privacy-first video embeds on home + /defence (2026-06-12)

Two FNPI YouTube videos are now on the public site as a new generic block type,
`video_embed`, behind a privacy-first facade. The site sells counter-surveillance,
so the page ships **zero third-party requests until the visitor chooses to play**:
a self-hosted poster and a play button, no iframe, no Google anything at load.

Code commit `e46392c` (waaseyaa/fnpi-waaseyaa main); infra bump `bb9a38e`
(FNPI_REF → e46392c); deploy run 27434296649 green.

## Block contract: `video_embed`

A generic, reusable block — `templates/blocks/video_embed.html.twig`, auto-resolved
by the page renderer (no registry). Fields:

| field | required | purpose |
|---|---|---|
| `video_id` | yes | YouTube id; used to build the nocookie embed URL **in JS only** |
| `title` | yes | aria-label ("Play video: …") and the no-JS fallback link text |
| `poster` | yes | self-hosted poster URL under `/img` (never i.ytimg.com) |
| `poster_alt` | yes | required alt text |
| `poster_w`,`poster_h` | opt | intrinsic poster size for the `<img>` (CLS belt-and-braces) |
| `caption` | opt | caption line under the player |
| `sec_h` | opt | eyebrow above the player |
| `h2` | opt | section title above the player |

**The facade.** A 16:9 box reserved by `aspect-ratio:16/9` (so there is no layout
shift when the iframe swaps in). Inside: the self-hosted poster (`object-fit:cover`),
a full-surface `<button class="vplay">` carrying only the bare `data-video-id` and an
`aria-label` with the title, and a `<noscript>` fallback `<a>` to
`youtube.com/watch?v=…`. The play glyph is the standard YouTube lozenge (84×60 visible;
the button itself fills the frame, far above the 44px target).

**The swap.** One click handler (`base.html.twig` footer script, delegated over all
`.vplay`) builds `https://www.youtube-nocookie.com/embed/<id>?autoplay=1&rel=0`,
removes the poster + button, and appends the iframe into the same frame. `autoplay=1`
means one click plays. This is the **only** place a YouTube URL is constructed; the
initial document carries no player resource.

**Privacy result (verified live, cold).** On both pages: `<iframe>` count 0,
`i.ytimg.com` 0, `googlevideo` 0, and zero `youtube/ytimg/googlevideo` URLs anywhere in
the served markup outside the click-handler `<script>`, the CSS `<style>`, and the
`<noscript>` fallback. The poster is served from our origin (`/img/...`, HTTP 200,
image/jpeg). A headless click triggered exactly one `youtube-nocookie.com/embed`
request and swapped the iframe in.

## Posters (self-hosted, optimized)

Downloaded once from YouTube, optimized, and committed to `public/img` — never
hotlinked.

| file | source | result | bytes |
|---|---|---|---|
| `video-china-trade-2012.jpg` | `zbxPX21K1gE` maxresdefault (1280×720, clean 16:9) | 1280×720, progressive q82 | ~110 KB |
| `video-fnpi-intro.jpg` | `AzmgGLEEt1U` sddefault (640×480, no maxres exists) | 640×360, **letterbox bars cropped** (top/bottom 60px black, confirmed at 0 brightness), progressive q82 | ~42 KB |

## Placements

- **Home**, immediately after the "Ten years in the room." photo_strip: `video_embed`,
  **no heading**, video `zbxPX21K1gE`, caption "2012 China Trade Mission." The dark theme
  carries straight through (`​.photo-strip + .video-embed` collapses the top padding) so it
  reads as a continuation of the track-record band. Home page `r36 → r37`.
- **/defence**, after the capability `module_grid`: `video_embed`, eyebrow **"Who we are"**,
  video `AzmgGLEEt1U`, caption "An introduction to First Nations Procurement Inc." Defence
  page `r6 → r7`.

Both videos are FNPI's own channel (@FNPI-Indigenous); no rights issue.

## Verification

- **Suite green:** 207 tests, 1562 assertions. New: a `video_embed` block-contract test,
  a shipped-poster test, block-sequence pins updated on both pages, and a privacy pin
  (no youtube/ytimg/googlevideo URL and no `<iframe>` in initial markup outside the
  handler script / style / noscript).
- **16:9 holds with no shift:** measured live in a real browser at 1280/768/375 — frame
  ratio 1.7778 at every width; poster→iframe swap moved the frame top 0px and changed its
  height 0px.
- **Re-ingest:** captions entered the index; total steady at 144 chunks (folded into the
  existing home/defence page chunks; the video entities are not themselves ingested).
- **Screenshots** (looked at), `storage/framework/_video_shots/`: facade + playing state,
  desktop + mobile, both pages. Facades show the poster + centered play glyph + caption;
  the playing shot shows the nocookie player loaded in the same box.

## FLAG for Matthew — 2011 vs 2012 (home page, same screen)

On the home page, directly above this video, the track-record **photo** caption reads
"Trade mission, Xi'an, China, **2011**", while the **video**'s own title (and our caption)
says "**2012** China Trade Mission." Both sit on the same screen. Per the directive the
Xi'an photo caption was **left untouched**. **Matthew to confirm:** two separate trips
(2011 and 2012), or one trip with a wrong year on one of them? Once confirmed it is a
one-field caption edit either way.

## Minor FYI (no action assumed)

The `/defence` "introduction" video's own YouTube thumbnail is a tactical-gear title card
("TACTICAL GEAR / MISSION READY"). Our caption frames it as "An introduction to First
Nations Procurement Inc." (the directive's exact text). If a softer intro frame is wanted,
the poster is a one-file swap — but nothing is wrong as shipped.
