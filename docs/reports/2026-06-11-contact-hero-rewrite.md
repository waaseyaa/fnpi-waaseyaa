# Contact hero rewrite (2026-06-11)

Follow-up to [2026-06-11-cta-sweep-and-doors.md](2026-06-11-cta-sweep-and-doors.md).
Content revisions only; no code change, no deploy needed. The contact page now pays off the
site-wide "Book an assessment" button instead of greeting visitors with a second generic ask.

## Revisions (contact, published r4 -> r9)

| Rev | Field | Before | After |
|---|---|---|---|
| r5 | hero eyebrow | Get started | Book an assessment |
| r6 | hero h1 | Tell us what you need. | Let's scope it. |
| r7 | hero intro | "Whether you are a Nation exploring a platform you own, a government or industry buyer, or a partner who wants to support the build, start here." | "Tell us what you're working with. We'll come back with a clear scope: what it takes, what it costs, and where to start." |
| r8 | title | Contact: Tell us what you need · FNPI | Contact: Let's scope it · FNPI |
| r9 | meta description | "Book an assessment, or tell us what you need. For First Nations exploring a sovereign platform, and for funders and partners who want to support the build." | "Book an assessment: tell us what you're working with, and we'll come back with a clear scope. For First Nations exploring a sovereign platform, and for funders and partners who want to support the build." |

Title and meta were the item-5 adjustments: the sweep-era wording carried "Tell us what you
need" twice on this page; both now align to the new h1 (the title takes it verbatim, the
meta implies it through the scope wording), with the funders/partners half unchanged. The
funders line beside the form and the form itself (fields, dropdown, Send) are untouched.
Each revision was guarded on the exact prior value; the final draft was machine-checked for
banned words and for any residue of the old phrase before publishing. Re-ingested: 135
chunks (1 created, 134 updated, 1 deleted).

## Item 6 check: phrase uniqueness

"Tell us what you need." now appears on exactly one page site-wide: the home closing-band
h2, where it sits above the two doors that answer it. Confirmed by a case-insensitive sweep
of all five rendered pages.

**One repetition flag (content decision, not made here):** the contact page now reads
slightly doubled within itself: the new intro says "Tell us what you're working with.
We'll come back with a clear scope…" and the form placeholder (from the CTA sweep) says
"Tell us a little about what you need, and we'll come back with next steps." Two "Tell
us…" openings and two "we'll come back with…" promises on one screen. If it grates, the
placeholder is the natural one to vary (e.g. "What are you working with? Even a rough
sketch is enough.") — one template line, flagged for Russell's call since the directive
froze the form.

## Verification

- Cold fetches: new eyebrow, h1, intro, title, and meta all serving; zero "quote"/"free"
  anywhere on any of the five pages; "Tell us what you need" gone from /contact and unique
  to the home band; funders line and Send intact.
- Screenshots (both looked at), `storage/framework/_shots/contact-hero/`:
  `contact-prod-1280.png` (prod cold: full hero + form) and `contact-375.png` (exact-width
  render of the prod-synced content: hero wraps cleanly under the burger header, form
  full-width).
