# The contact form works now (2026-06-11)

Follow-up to [2026-06-11-ai-elevation.md](2026-06-11-ai-elevation.md). The public form
actually submits, stores in Anokii, and staff read it where they already work.

## What shipped

- **Endpoint:** `POST /contact/submit` (SiteServiceProvider). Classic form-encoded POST, no
  JS required. Email is the only required field, format-validated server-side; name,
  organization, topic, and message are optional and the labels say so honestly. Success
  renders "Got it. We'll come back to you." in the house voice; a bad email re-renders the
  form with everything the visitor typed preserved (422).
- **CSRF:** the framework's own session token, carried as a hidden `_csrf_token` field. The
  token reaches the page template through a lazy `csrf_token` Twig global registered at
  render time (`CsrfMiddleware::token()`, the helper the framework documents for templates);
  no route exemption. A tokenless POST gets the framework 403 (verified live).
- **Spam hygiene, no third-party vendors:** (1) honeypot field, visually hidden, any value
  drops the post; (2) minimum-fill-time stamp rendered into the form: posts faster than 4
  seconds, with forged/future/non-numeric stamps, are dropped; (3) per-sender rate limit, 5
  per hour, keyed on a salted truncated HMAC of the IP (`contact_rate` table; **no raw IP is
  stored anywhere, in any form**). All three traps render the normal success page and store
  nothing, so abusers learn nothing.
- **Entity:** `contact_submission` (non-revisionable; email, name, org, topic, message,
  submitted_at, source_path, is_read). Table lands via the deploy's `db:init
  --sync-schema` (confirmed created on prod).
- **Access posture:** `ContactSubmissionAccessPolicy`, the tightest in the workspace. Any
  signed-in staff account reads; marking read or deleting requires the new `manage inbox`
  permission (Editor and Admin); **create is never granted through the entity gate**: only
  the public endpoint writes, server-side. The MCP agent's read/write allowlists exclude
  the type (personal contact data), pinned by test; the in-app chat agent's
  WORKSPACE_TYPES never included it.
- **Staff surface:** the **Anokii Inbox** (`/anokii/inbox`), a live module in the sidebar.
  Newest first, unread dot + count, mark-all-read. Deliberately just a readable list (v1).
- **Mail: NOT configured, deliberately not bodged.** The app has no outbound transport (no
  `mail` config key, no SMTP/SendGrid env, the vendored mail package defaults to a local
  log file; the mailer interface is framework-internal). No per-submission email to
  matthew@/russell@ this run; the Inbox is the channel. **Follow-up:** add a `mail` config
  block + a real transport (and confirm upstream whether app code may consume the internal
  MailerInterface) when notification is wanted.

## Verification

- Suite: **158 tests, 887 assertions**, green. Eleven new tests: email-only validation,
  error re-render with preserved values, honeypot, fill-time (too fast / future / forged /
  missing), rate-limit cap, entity registration (non-revisionable) and round-trip, the
  access matrix (anonymous denied, staff read, viewer cannot mark read, manage-inbox can,
  create denied for everyone), MCP exclusion (out_of_scope both directions), signed-out
  inbox redirect + 401, and the live Inbox module.
- Deploy: fnpi-waaseyaa `fbff410` (merge of contact-form-live); infra `97f8f95`; run
  27351401710 green. `contact_submission` table created by the deploy's schema sync.
- **Local end-to-end through the real HTTP stack:** GET /contact (real 64-char CSRF token
  rendered), email-only POST stored + confirmed; invalid email 422 with values preserved;
  tokenless POST 403; honeypot 200-success with nothing stored; authed Inbox (legit local
  test account via anokii:invite + app:assign-role editor) listed the submission with the
  unread marker; mark-all-read returned `{"ok":true,"marked":1}`.
- **Prod end-to-end:** two real submissions through the live site (email-only and full
  entry), both confirmed with the success page and both present in the prod store with
  correct fields; **both test entries deleted afterward** (store back to zero), local test
  rows cleaned too.
- Screenshots (all looked at), `storage/framework/_shots/contact-live/`:
  `form-prod-1280.png` (live form: honest required/optional labels, Send),
  `confirmation-1280.png` (the Got-it page), `inbox-1280.png` (the Anokii Inbox with the
  live module in the sidebar, unread dot, mark-all-read).

## Flag list (carried forward)

- **Outbound mail** is the declared follow-up above.
- **The duplicate phrasing between the contact intro and the form placeholder is still
  open** (the form was frozen for copy this run; vary the placeholder next time contact
  copy is touched).
- Prior flags hold: AI-to-core pillar line for Matt, values one-liners page-side, pillar
  commas, tagline, "10+ years", defence stage labels, INDGEN approval.
