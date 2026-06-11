# Photo placement + a leak found and purged (2026-06-11)

Follow-up to [2026-06-11-anokii-analytics.md](2026-06-11-anokii-analytics.md). Two stories:
the planned photo run (clean), and a pre-existing exposure the post-run audit caught (now
remediated, one step left for Russell).

## 1. The photo run

**New block: `photo_strip`** — optional eyebrow + h2 over 1-3 captioned figures on the
house dark band. Contract: `{sec_h?, h2?, photos: [{src, alt, caption}]}`. Images keep
natural aspect ratios (no object-fit cropping, no stretching), subtle radius + border,
muted small captions, lazy loading; alt text is required and pinned by test. Side by side
on desktop, stacked from the 880px breakpoint (full-width at phone widths by the same
rule). Block-sequence test updated; a second test pins that every seed photo references a
shipped file and carries alt.

**Derivatives (approved images only; originals untouched in the Drive, verified
byte-identical after the run):**

| File | From | Size | Placement |
|---|---|---|---|
| `/img/tmx-market-open.jpg` | Drive #7 | 1200x1600, 342 KB | home Track record strip |
| `/img/xian-trade-mission-2011.jpg` | Drive #10 | 1600x1067, 147 KB | home Track record strip |
| `/img/fnpi-booth-faraday.jpg` | Drive #4, left-edge crop (bystander removed) | 1600x1429, 382 KB | **STAGED** for /faraday next run; referenced by no page yet |

All JPEG q82 progressive, max 1600px long edge, EXIF-free (audit-verified).

**Placement:** home **r29** (published r28 -> r29): the strip sits after the stats band,
before the sovereign-AI band. Eyebrow "Track record", h2 "Ten years in the room.", the TMX
and Xi'an photos with the directive's captions and alt text verbatim (audit-verified
character-for-character; apostrophes HTML-encoded, decode identically). Re-ingest: 137
chunks (the captions entered the index; TMX caption confirmed present).

**Verification:** suite 164 tests green; deploy run 27359637231 green; cold probes confirm
markup, captions, alts, and all three images serving 200 at the listed sizes; layout
measured at 375 (single column, aspect preserved, no overflow) and eyeballed at 1280/768/375
(`storage/framework/_shots/photo-strip/`, incl. `home-prod-1280.png` — faces uncropped,
no stretching, captions legible).

## 2. The audit finding: Drive originals were in the public repo

The adversarial post-run audit found that **all ten Drive originals — including the three
BORAN VII / Türkiye military images and the unconsented-faces shots — had been git-tracked
at `resources/seed/global-relationships/` in the public GitHub repo since the drive-module
commit (`ace721a`, 2026-06-07)**, alongside a README describing them as Matthew's personal
proof photos. This predates the recon and this run; the placement itself shipped only the
three approved derivatives (audit-verified: public/img contains exactly the permitted
files, the recon folder was never tracked, no template references a forbidden path).

**Remediation taken, in order:**
1. Removal commit pushed immediately (took the directory out of browsable HEAD).
2. Full history rewrite with git-filter-repo (`--invert-paths` on the directory): zero
   references to the folder remain anywhere in history. A pre-rewrite mirror backup sits
   locally at `Local Sites/_fnpi-prerewrite-backup.git` (contains the originals; local
   only; delete once confident).
3. Force-pushed `main` (the only remote branch). New tip `df37696`; all post-`ace721a`
   SHAs changed.
4. **FNPI_REF re-pinned to `df37696`** (infra `8e07523`) and redeployed green, since the
   old pinned SHA no longer exists on the remote. Consequence: FNPI_REF rollbacks to
   pre-rewrite SHAs are no longer buildable; rollback continues to work at the
   page-revision layer as always.
5. Fresh-environment note: `app:seed-drive --dir=resources/seed/global-relationships` now
   has no in-repo source; prod's Drive already carries the files on the volume, and the
   deploy pipeline never ran seed-drive, so nothing operational broke. Fresh installs
   seed the Drive from a private source going forward.

**THE ONE REMAINING STEP IS RUSSELL'S:** GitHub still serves the old blobs to anyone who
already knows the exact pre-rewrite SHAs (verified: a raw URL with the old commit SHA
still returns 200 from cache). Discoverability is near zero — the files are unreachable
from every branch, listing, and search — but full removal needs a GitHub Support request
to purge cached/unreachable objects for `waaseyaa/fnpi-waaseyaa` (repo admin; reference
"sensitive data removal, force-pushed history"). Until then, do not share pre-rewrite SHAs.

## Flags

- **Matt must be told which photos are now public** (his face, his photos): the TMX
  market-open shot and the 2011 Xi'an city-wall shot are live on the homepage; the booth
  photo with Premier Ford is staged (in the public repo, displayed nowhere yet) for the
  /faraday page. He should also be told about the four-day repo exposure of the full set
  and its remediation.
- #4 staged for /faraday with planned caption "The FNPI booth: the Ghost Mode Faraday
  line in the field."
- The Xi'an alt text says "walking" per the directive; the photo is a posed standing
  portrait. One word, Russell's wording, flagged for accuracy.
- Prior flags carry forward (mail transport, contact placeholder doubling, AI-to-core
  pillar line, values one-liners, pillar commas, tagline, "10+ years", defence stage
  labels, INDGEN approval).
