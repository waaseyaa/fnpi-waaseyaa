# Faraday commerce: /faraday + live Stripe checkout (2026-06-11)

The Faraday store is **live and selling** on fnprocure.ca. Real prices, live Stripe payment
links, real checkout. Built test-then-live in spec, shipped live-direct per Russell's call
("only doing this once, used the live key").

## Live now

- **/faraday** published (page rev 1): hero, three product sections, bulk/institutional
  band, booth photo, shipping/returns line.
- **Three SKUs, real prices** (Matthew via Russell): Faraday phone case **$20**, utility
  case **$15**, key fob 2-pack **$10** CAD. Placeholder wording dropped.
- **Buy buttons hit live Stripe checkout** (verified end to end, screenshot below): correct
  price, CA $15 / US $25 flat shipping selectable, adjustable quantity, automatic tax.
- **Entry points:** Faraday in the top nav + mobile menu on all six pages (between Defence
  & Security and Contact); home Faraday band CTA now "Shop Faraday cases" -> /faraday
  (home r29 -> **r30**); defence materiel module's Faraday sentence links to /faraday
  (defence r5 -> **r6**).
- Code: fnpi-waaseyaa `e1b4f7c`; infra `70d8902`; deploy 27368132113 green. Suite 171
  tests. Ingest 144 chunks / 12 sources (/faraday added).

## Stripe object IDs (LIVE mode, account acct_1TgxLu2aTPxr7wdw, country CA)

| SKU | Product | Price (CAD) | Payment link | URL |
|---|---|---|---|---|
| Phone case | `prod_UgaDW8iOetkFZO` | `price_1ThD2v2aTPxr7wdwOFxuBJnQ` ($20) | `plink_1ThD2y2aTPxr7wdwXkfvUGih` | https://buy.stripe.com/5kQ4gs2bQ5fbezScup6g800 |
| Utility case | `prod_UgaDTDNYgBtR92` | `price_1ThD2w2aTPxr7wdwxdsnqtWj` ($15) | `plink_1ThD2y2aTPxr7wdwqpwSTZwj` | https://buy.stripe.com/aFa7sE03IcHDbnGeCx6g801 |
| Key fob 2-pack | `prod_UgaDEVBLNDzbs2` | `price_1ThD2w2aTPxr7wdwWbc743RT` ($10) | `plink_1ThD2z2aTPxr7wdwG6hRxel9` | https://buy.stripe.com/5kQ00cdUybDz2Racup6g802 |

Shipping rates: CA `shr_1ThD2x2aTPxr7wdwGEf3riT3` ($15), US `shr_1ThD2x2aTPxr7wdwkscnXU7u`
($25). All three links: active, CA+US address collection, both flat rates offered as
buyer-selectable options (Stripe payment links can't auto-pick by country), quantity
adjustable 1-10, automatic tax enabled. Provisioning output saved at
`storage/framework/_stripe_live_provision.out`; the keyed-on-env script at
`storage/framework/_stripe_provision.sh`.

## Checkout evidence

`storage/framework/_shots/faraday-live/stripe-checkout-phone.png`: the live phone-case
checkout, **Pay FNPI CA$35.00** (= $20 item + $15 Canada shipping), Qty selector, both
shipping methods, Tax "Enter address to calculate", Card + Klarna + Link. No real card was
pushed (live mode; the `4242` test card only works in test mode, so the first true
end-to-end charge is the first real customer). Page screenshots:
`faraday-prod-1280.png`, `faraday-prod-mobile.png` (three sections, branded panels, Buy
buttons, bulk band, booth photo; stacks cleanly on mobile).

## Required flags

- **Stripe Tax: active.** It was already configured on the account, so per directive I
  enabled automatic tax on all three links (it calculates once the buyer enters an
  address). Not improvised.
- **Payouts: PAUSED.** `payouts_enabled: false` — a required account task is past due
  (dashboard banner). Charges will capture but **will not pay out to the bank until Russell
  completes that task.** Not a blocker for selling; is a blocker for receiving the money.
- **Margin unknown.** Prices are Matthew's real numbers, but landed cost is still unknown,
  so per-unit margin can't be computed yet.
- **Shipping amounts are placeholders** ($15 CA / $25 US flat) pending real rates.
- **Returns policy: OPEN.** The page says only "Ships from Canada. Questions or returns:
  contact us." No formal returns terms yet — decide and add before volume.
- **GoDaddy store overlap: OPEN.** The retired GoDaddy Website-Builder store may still list
  these products elsewhere; decide whether to take it down so there's one source of truth.
- **Live-direct, no test charge.** Going straight to live (vs test-then-swap) means the
  flow's first real validation is a real customer's card. The checkout page itself is
  verified correct; the payment rail is unproven until the first sale.

## Tomorrow's footage drops in with no restructuring

`faraday_feature` now renders an HTML5 `<video>` (poster + controls, no autoplay) when a
block carries `video` (mp4 URI) + optional `video_poster`, or a still `image`, else the
branded panel. Each product section is a `faraday_feature`; when Matthew's stills/video
arrive, add `image` or `video` to the block (a one-field content revision) and it replaces
the panel in place.

## Note on the build

The earlier "three SKUs" commit (`f5904b7`) had silently lost its seed edit and would have
shipped the placeholder two-SKU page at the wrong prices ($39/$59); the pre-merge live-link
verification caught the mismatch and it was corrected before anything reached prod. The
go-live publish script's `_stack_faraday_entries` guard also caught that the photo_strip
run had shifted the home Faraday band's index, and aborted cleanly before re-locating the
band by content. Nothing wrong shipped.
