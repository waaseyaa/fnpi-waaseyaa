# FNPI Technology lane: living summary

**Purpose:** the one-page, continually-updated read on how FNPI expands through the Technology and
Software lane. This is a documentation/index layer: the canonical sources are
`fnpi-tech-lane-business-model.md`, `tech-lane-template.md`, `fnpi-master-plan.md`, and
`FNPI-market-model.xlsx`. When those change, update this. No em dashes (house style). Modeled
figures are labelled modeled, not contracted.

**Last updated:** 2026-06-06 · **Maintainer:** Russell (with Cowork) · **Status:** active build

## The thesis in one paragraph

Move a First Nation's entire digital operation onto a sovereign platform the Nation owns (hosted in
Canada, governed by Council, OCAP aligned, AI native) instead of renting it from US vendors. The
incumbent displaced is Microsoft 365: band governments are excluded from Microsoft's nonprofit
pricing, so a 60 to 70 person band already pays about $12K to $17K a year, forever, for software it
does not own. The model redirects that recurring spend into a stack the Nation owns and adds
everything Microsoft never provided. Revenue is recurring per Nation plus a one time migration fee,
compounding as each Nation moves more of its stack across. The same engine later turns outward to
corporates as INGEN.

## Brand architecture (one engine, two products, one moat)

- **Waaseyaa** - the engine (open source, entity first, AI native PHP framework; Russell's).
- **Anokii** - the product for Nations: sovereign workspace (Website, Member Portal, Drive,
  Co-Intelligence, Data Rooms, Governance, Vault).
- **Co-Intelligence** - Anokii's built in AI, grounded on the Nation's own files. Core, not an add on.
- **INGEN** - the product for companies (same engine pointed outward, public/aggregate data only).
- **FNPI** - the company that sells it all on its Indigenous procurement qualification.

## The motion: land and expand

- **Land** with the visible, needed entry point: website + working member portal.
- **Prove** sovereignty is real: Canadian hosting, OCAP, Council governance.
- **Expand** into department modules one at a time, each recurring and compounding.
- **Migration sequence:** website -> member portal -> department data (off spreadsheets and legacy
  apps) -> files/docs/collaboration (displaces SharePoint/OneDrive/Teams) -> email last (built on
  Web Networks, which already runs mail).
- A Nation climbs a ~4 year ladder: Wedge -> Hook -> Infrastructure -> Moat, from ~$13K in year 1
  to the full ~$23,825/year at maturity.

## Why full replacement is buildable now

- Mature self hostable office components already exist (OnlyOffice, Collabora, Nextcloud): assemble,
  do not invent.
- AI collapses the cost of the Nation specific pieces no office suite has (member portal, Section 87
  payroll, ISC reporting, housing, registry, governance/BCRs): the whitespace competitors miss.

## Market and money (modeled, from FNPI-market-model.xlsx)

- ~580 serviceable Nations (of 646), segmented by size; platform $9K to $18K/year.
- Department modules sold one at a time: housing, income assistance, finance/ISC reporting,
  membership, governance, health/Jordan's Principle, lands, emergency management, HR/payroll (S.87).
- Blended ~$23,825 per Nation per year at maturity + ~$12K one time migration.
- TAM/SAM ~$13.8M/year recurring. Year 5 SOM: ~$1.24M (worst) / ~$2.48M (likely) / ~$3.86M (best).
  Crosses ~$2.5M recurring around Year 6. Benchmarked to Xyntax (100+ Nations), OneFeather (270+).

## The moat (only FNPI has all four)

1. **It is the procurement vehicle** - 100% First Nations owned, on both registries (ISC + CCAB), so
   Nations buy via the mandatory federal 5% Indigenous procurement set aside + ISC funding, often
   paying little or nothing from band funds. No platform competitor is also a procurement company.
2. **Sovereignty is real** - Canadian incorporation/residency, per Nation isolation, OCAP. Answers
   US CLOUD Act exposure.
3. **It owns the technology** - Waaseyaa is Russell's, not a resold platform.
4. **It is already built** - the Sheguiandah reference build (full site migrated onto Waaseyaa,
   488 entities) plus a live portal engagement.

## Delivery model: the AI Operator

- Goal: high touch service at every Nation without a Russell at each one.
- A local **AI Operator** at each Nation (not a technical hire; the existing office hub, e.g. Deandra
  the Sheguiandah receptionist), amplified by the built in AI.
- Layers: product at the base -> local operator for daily touch -> small central team (build, train,
  escalate) -> fractional operators for small Nations.
- A triage rule (configure if it exists / build once if common / self serve or paid if one off)
  keeps it from drifting into an unscalable custom shop.
- "AI Operatives training AI Operators": OCAP aligned, keeps capability and money in the community.

## Productization gap (1 -> 100 Nations; this is the tech roadmap)

- Repeatable instance provisioning (currently a manual dev machine command).
- Per Nation branding pipeline (logo/colours/content intake).
- Backend editor experience demoing cleanly (the differentiator vs WordPress/Drupal).
- A SaaS layer on Web Networks (billing, onboarding, updates, monitoring).
- Support/training as a productized service.
- Infrastructure roadmap: start on Web Networks -> long term First Nations owned datacenter.

## INGEN (corporate extension; described, not yet modeled)

- Same engine for companies that must engage Nations: find/verify Indigenous suppliers, Duty to
  Consult, ESG/reconciliation reporting.
- Illustrative ~$50K/corporate client/year. Even a modest foothold could outgrow the FN lane.
- Guardrail: public/aggregate data and FNPI's network only, never a Nation's private data.

## Funding the build (non dilutive first)

- Aboriginal Entrepreneurship Program (up to $99,999 individual / $250,000 community owned), FedNor,
  Indigenous Financial Institutions, Raven Capital if an equity path is chosen.

## Open decisions / risks

- Prune the offerings catalog (`fn-offerings-explorer.html`): the next unblock into the business plan.
- Confirm modeled prices/attach rates; pull a real band's Microsoft bill to ground the displacement case.
- Define the deal structure between Russell and FNPI (equity/role/pay).
- Posture toward Tsen'awt (newest competitor): partner or parallel.
- Conflict of interest: the Sheguiandah relationship runs through Matthew (SFN employee and FNPI
  president); manage with disclosure/recusal.

## Throughline

Prove it once (Sheguiandah, done) -> make it repeatable (the productization list) -> sell it on the
procurement moat -> point the same engine at corporates via INGEN.

## Change log

- 2026-06-06: Created from `fnpi-tech-lane-business-model.md` and `tech-lane-template.md`.
