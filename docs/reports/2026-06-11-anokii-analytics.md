# Analytics in the Anokii dashboard (2026-06-11)

Follow-up to [2026-06-11-contact-form-live.md](2026-06-11-contact-form-live.md). Russell's
ask: analytics in the dashboard, our own, no third party. The site has had exactly that
since launch (the oiatc-ported first-party system: cookieless beacon -> `/api/collect` ->
`analytics_event` in FNPI's own SQLite); what changed is where it surfaces and who can see
it.

## What shipped

- **Anokii Analytics module** (`/anokii/analytics`): totals (pageviews, unique visitors),
  per-page views/visitors/average scroll/average dwell, referrers, devices, with a
  date-range picker (default last 30 days, UTC). Staff-gated exactly like every workspace
  module; live in the sidebar and on the dashboard tile grid. The data pipeline is
  untouched: same beacon, same collector, same table, nothing leaves the Nation's database.

- **Closed a real exposure found during recon:** the old standalone `/admin/analytics`
  page was **publicly reachable on prod** (verified live: 200, full dashboard, no auth).
  Its app-layer comment assumed "Caddy basic auth on /admin/* where configured", and it was
  never configured for this site. The route now 302s to the gated module (old bookmarks
  land at the sign-in page when signed out), and the standalone controller + template are
  deleted so the ungated surface cannot come back by accident.

## Verification

- Suite: **162 tests, 899 assertions**, green. New tests pin the live module + href, the
  signed-out redirect, the report numbers against a seeded event table, the route
  registration, and the retirement of the old controller.
- Code: fnpi-waaseyaa `e20c696` (merge of anokii-analytics); infra `c66ed7f`; deploy run
  27352979790 green.
- Prod, cold: `/admin/analytics` -> 302 -> `/anokii/analytics` -> (signed out) 302 ->
  `/anokii/login` (200 sign-in). The beacon still collects (POST /api/collect -> 204).
- Authed rendering verified against the same merged code with the local test account
  (editor role): real report data renders in the shell. Screenshot (looked at):
  `storage/framework/_shots/analytics/anokii-analytics-1280.png` (the module live in the
  sidebar, stat cards, pages table with scroll/dwell, referrers, devices). Note: the "Â·"
  glyphs in that screenshot are an artifact of the file-based capture, not the live page.

## Notes

- The beacon records `/anokii/*` workspace paths too (it always has). First-party,
  staff-only viewing, so no action taken; trivially excludable in the beacon if Matt or
  Russell prefer public-site-only numbers.
- Flag list carried forward unchanged (mail follow-up, contact placeholder doubling,
  AI-to-core pillar line, values one-liners, pillar commas, tagline, "10+ years", defence
  stage labels, INDGEN approval).
