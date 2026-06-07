# FNPI Technology and Software lane: business model

**Prepared:** 2026-06-04 · **For:** Russell and Matthew · **Scope:** the Technology and Software
lane only (the Sourcing and Protection lanes are separate). No em dashes. Every figure is either
pulled from `FNPI-market-model.xlsx` (labelled modeled) or from a cited public source. Inputs that
still need Matthew's or a buyer's confirmation are flagged. INGEN (the corporate lane) is described
but not yet in the revenue numbers.

## 1. The model in one paragraph

FNPI moves a First Nation's entire digital operation onto a sovereign platform (Anokii, built on
the Waaseyaa engine) that the Nation owns and that is hosted in Canada under Council's control. The
end state is the full stack: website, member portal, files, documents, collaboration, department
systems, and eventually email, all on infrastructure the Nation owns instead of rented from US
vendors. The incumbent being displaced is Microsoft 365, which a band pays commercial per seat
rates for today because band governments do not qualify for Microsoft's nonprofit pricing. Revenue
is recurring per Nation plus a one time migration fee, and it compounds as each Nation moves more of
its stack across. The moat is that FNPI is itself an Indigenous procurement vehicle, so a Nation can
buy through the federal 5 percent set aside and ISC funding rather than out of band funds. The same
engine later turns outward to corporates as INGEN.

## 2. The problem and the value proposition

A First Nation today runs on a stack it does not own and cannot fully govern. The productivity layer
is **Microsoft 365**, paid for at commercial rates (band governments are excluded from Microsoft's
nonprofit program, so there is no free or discounted grant for them), with the data living in a US
reachable cloud. Around it sit a neglected website, finance software, spreadsheets for housing, an
outside payroll service, and old desktop band administration apps. Nothing connects, the sensitive
records are exposed to US law under the CLOUD Act, and members barely see any of it.

The value proposition is full digital sovereignty: **one platform the Nation owns**, hosted in
Canada, governed by Council, OCAP aligned, member facing, and AI native. Not a tool added on top of
Microsoft, but the system that replaces it. Three reasons a Nation says yes: it is theirs (ownership,
Canadian hosting, control), it is one connected system across every department with one record and
one login, and it is member facing with an assistant grounded on the Nation's own files.

**The money is already leaving the community.** A 60 to 70 person band administration already pays
Microsoft on the order of $12K to $17K a year (commercial per seat, see section 9), every year,
forever, for software it does not own. Our model redirects that recurring spend into a sovereign
stack the Nation owns, and adds everything Microsoft never provided. So our price is not competing
against free; it is competing against rent the Nation is already paying to a US vendor.

## 3. Why full replacement is buildable now

The reason "do not rebuild Microsoft" used to be sound is that the build cost was enormous. That has
collapsed, on two fronts:

- **Mature, self hostable, open source office components exist today.** OnlyOffice, Collabora
  Online, and Nextcloud already provide documents, spreadsheets, real time co authoring, and file
  storage, running on a Nation's own infrastructure. The productivity layer is assembled and
  integrated under one sovereign login, not invented from scratch.
- **AI collapses the cost of the Nation specific pieces** that no office suite has: the member
  portal, Section 87 payroll, ISC reporting, housing and registry, governance and BCRs. That is
  where build effort actually goes, and it is exactly the whitespace no competitor occupies.

Email at scale (deliverability, spam, outside world interop) is the hardest piece, but it is less
of a blocker than it first looks: **Web Networks, our hosting foundation, already runs mail
systems.** So email is either built on top of their existing mail infrastructure or rebuilt with
them as a modern, enterprise grade system, not invented from zero. It still migrates last because
it is the least sovereignty sensitive, but the capability is in reach through the hosting partner.

## 4. Customers and segments

**Primary (this lane): First Nations bands.** 646 bands in Canada (ISC Indian Register 2024). 580
are the serviceable market, defined as bands with total registered population of 250 or more
(FNPI-market-model.xlsx, Assumptions and Distribution). Segment by size, which also sets price:

- 250 to 999 members: platform at $9,000 per year. 250 bands.
- 1,000 to 1,999 members: platform at $9,000 per year. 145 bands.
- 2,000 plus members: platform at $18,000 per year. 185 bands.
- Under 250 members: a $4,000 platform tier, treated as below the serviceable line.

**Buyer inside the Nation:** Council and the band administration (the director, department managers,
the Communications Officer, the Member Clerk). Sheguiandah is the live proof of this buyer.

**Secondary (future, INGEN lane): corporates** that must engage Nations (Duty to Consult, ESG and
reconciliation reporting, Indigenous supplier verification). Higher value per client, not yet
modeled here.

## 5. The offering and pricing

Itemized from FNPI-market-model.xlsx (Products tab). Attach is the share of the 580 serviceable
Nations expected to buy that item at maturity. Prices and attach rates are modeled inputs to turn as
real deals come in.

**Core (every Nation, inseparable)**
- Platform (website, member portal, sovereign workspace, documents and files): tiered, weighted
  average about $11,026 per year, attach 90 percent. Displaces the Microsoft 365 productivity and
  collaboration stack, built on the open source components in section 3.
- Co-Intelligence (AI): about $3,000 per year, attach 90 percent. **AI is core, not an add-on.**
  There is no product without the AI; it ships with the platform, grounded on the Nation's own
  files, and every Nation has it. Modeled at platform attach, not as an optional module. (This is
  the change from the earlier model, where it was wrongly treated as a 50 percent add-on.)

**Department modules** (each recurring, sold one at a time after the platform)
- Housing and asset management: $3,500 per year. Attach 40 percent.
- Income assistance / social development: $3,500. Attach 35 percent.
- Finance and ISC reporting: $4,000. Attach 35 percent.
- Membership / registry: $3,000. Attach 45 percent.
- Governance (council, BCR, elections): $2,000. Attach 40 percent.
- Health intake / Jordan's Principle: $3,000. Attach 20 percent.
- Lands / land code administration: $3,000. Attach 15 percent.
- Emergency management: $2,000. Attach 15 percent.
- HR and payroll with Section 87: $4,680. Attach 15 percent (replacing incumbent payroll is slow).

**Cross cutting services**
- Hosting and data residency: $2,500 per year. Attach 65 percent.
- Managed support: $3,000 per year. Attach 45 percent.
- One time migration and implementation: $12,000 per Nation.

**Standard capability: meeting minutes transcription** using Whisper, run locally so the audio and
minutes stay in Canada under the Nation's control. Pairs with the governance module. A recurring
need at every Nation, so it is a standard offering.

## 6. Revenue model and the displacement case

- **Recurring per Nation.** Blended average of about $23,825 per year at maturity (platform plus
  adopted modules and services). Modeled.
- **One time per Nation.** $12,000 migration and implementation.
- **Net of displaced Microsoft spend.** Against the $12K to $17K a year a band already pays Microsoft,
  the true incremental cost of the sovereign platform is smaller than the headline number, and the
  Nation gets ownership plus all the modules Microsoft never offered. This is the core of the sales math.
- **Compounding.** Land, then migrate more of the stack across. Net revenue retention is modeled at
  1.05, so an existing Nation's spend rises about 5 percent a year before new modules.
- **The motion:** land and expand toward full replacement (see section 8).

## 7. Market size

- **TAM / SAM (this lane):** about $13.8 million per year in recurring revenue if all 580
  serviceable Nations are won at the modeled prices and attach rates (FNPI-market-model.xlsx, with
  AI core at platform attach). Blended ARPA about $23,825 per Nation.
- **SOM by year five, three scenarios:**
  - Worst: about 52 Nations, about $1.24M ARR.
  - Likely: about 104 Nations, about $2.48M ARR.
  - Best: about 162 Nations, about $3.86M ARR.
  - Penetration benchmarked to real incumbents: Xyntax 100 plus Nations (about 16 percent),
    OneFeather 270 plus (about 43 percent ceiling).
- **The climb: Wedge to Moat takes about four years per Nation.** A Nation does not buy the
  whole stack on day one. It lands on the wedge (website, portal, hosting, migration) and grows
  up the ladder as trust and adoption build. Educated ramp, as a share of the mature blended ARPA
  of $23,825:

  | Tenure year | Stage | What they add | ARPA | % of mature |
  |---|---|---|---|---|
  | Year 1 | Wedge | Website, member portal, AI, hosting (plus $12K one time migration) | ~$13,100 | 55 percent |
  | Year 2 | Hook | Full sovereign workspace, support, first growth | ~$17,900 | 75 percent |
  | Year 3 | Infrastructure | Department modules | ~$21,400 | 90 percent |
  | Year 4 plus | Moat | Full adoption, procurement funded, ~5 percent NRR after | ~$23,825 | 100 percent |

  (Co-Intelligence is in from Year 1, since AI is core to the platform, not a later add-on.)

- **Five plus two year projection (Likely acquisition, with the ramp applied).** This replaces the
  old projection, which assumed each Nation hit full ARPA in its first year. The ramp is more
  conservative and back loaded, and it is the honest shape.

  | | Yr 1 | Yr 2 | Yr 3 | Yr 4 | Yr 5 | Yr 6 | Yr 7 |
  |---|---|---|---|---|---|---|---|
  | New Nations | 5 | 12 | 22 | 30 | 35 | 35 | 35 |
  | Cumulative Nations | 5 | 17 | 39 | 69 | 104 | 139 | 174 |
  | Recurring revenue | $66K | $247K | $610K | $1.16M | $1.88M | $2.68M | $3.55M |
  | Implementation (one time) | $60K | $144K | $264K | $360K | $420K | $420K | $420K |
  | Total revenue | $126K | $391K | $874K | $1.52M | $2.30M | $3.10M | $3.97M |

  The lane crosses about $2.5M in recurring revenue around Year 6, roughly a year later than the
  unramped model implied, because most Nations are still mid climb at Year 5. New Nations per year
  and the ramp percentages are the two biggest levers; both are tunable assumptions, not facts.

## 8. The migration: what we move and the end state

We migrate everything onto the sovereign system, in a sequence that lands fast and de risks each
step. None of these are about keeping Microsoft; they are the path off it.

1. **Website** (WordPress, Elementor, or the GoDaddy builder) into Waaseyaa. The proven Sheguiandah path.
2. **Member portal.** Usually broken or absent, so it is build new, the connective wedge between
   membership data and services.
3. **Department data** off spreadsheets and legacy desktop band admin apps (the Xyntax and Ferrus
   class), where there is real pain and no incumbent loyalty.
4. **Files, documents, and collaboration** onto the sovereign suite (the open source components in
   section 3). This is the step that displaces SharePoint, OneDrive, Teams, and the Office documents,
   moving the productivity layer the Nation currently rents from Microsoft onto infrastructure it owns.
5. **Email last.** Built on or rebuilt with Web Networks (which already runs mail), so it is
   reachable; it migrates once the rest is bedded in because it is the least sovereignty sensitive.

**End state:** the Nation's entire digital operation, website to email, on sovereign, Canadian,
Council governed infrastructure, owned rather than rented. That is the product.

**Infrastructure roadmap.** All of it starts on **Web Networks** (Canadian, Indigenous aligned,
already running web and mail). That is the foundation we build on now. The longer term goal is to
move onto a **First Nations owned and operated datacenter** once one is available, so the hosting
layer itself becomes sovereign, not just the software. Web Networks is the on ramp; a FN datacenter
is the destination.

## 9. The moat and the incumbent

Four moat layers, and only FNPI has all four together:

1. **We are the procurement vehicle.** Since April 2022 every federal department must direct at least
   5 percent of annual contract value to Indigenous business (about $1.24B, 6.1 percent in FY2023-24,
   with "IT solutions and services" named by ISC). FNPI is 100 percent First Nations owned, operating
   since 2017, qualified on both registries (ISC Indigenous Business Directory and CCAB/CCIB Certified
   Indigenous Business). No competitor is also a procurement company.
2. **Sovereignty is real.** Canadian incorporation, Canadian data residency, per Nation isolation,
   member access and export rights, contractual OCAP alignment. This answers the US CLOUD Act exposure
   that Microsoft and every US incorporated provider carries.
3. **We own the technology.** The Waaseyaa engine is Russell's; FNPI is not reselling someone else's platform.
4. **It is already built.** A complete Sheguiandah reference build and a department module in production.

**The real incumbent: Microsoft 365.** Band governments are excluded from Microsoft's nonprofit
program (governments are explicitly ineligible), so a Nation pays commercial rates, roughly $12.50
per user per month (Business Standard) to $22 (Business Premium), with data in a US reachable cloud.
That is the spend and the exposure we displace. Note also Microsoft ended its free Business Premium
and Office 365 E1 nonprofit grants on July 1, 2025, but that never applied to band governments anyway.

**Other vendors (Nation specific, narrower than us):**
- Xyntax (Indigenous owned, finance/ERP, on premises since 1984, 100 plus Nations): integrate with
  finance, do not rebuild it; the base penetration benchmark.
- OneFeather (Indigenous owned, cloud, membership, elections, 270 plus Nations): the penetration
  ceiling and nearest cloud peer, weak on the full department suite.
- Animikii (Indigenous B Corp, custom web and data sovereignty): an ally to coordinate with.
- Mustimuhw (Indigenous owned health EMR): integrate, do not compete on health records.
- Tsen'awt (just launched, per seat $35 to $120 per seat per month): the closest new platform
  competitor; posture is an open decision (partnership decisions.md D1).
- GovPilot ($6,500 to $20,600 per year, population banded): a US municipal pricing comparator.
- Note: the First Nations Procurement Authority (FNPA, incorporated May 2025) is building a buyer pays
  certification registry; watch as the lane's emerging certifier.

## 10. Cost and operations

- **Build:** the engine exists; cost is integrating the open source productivity components and
  building the Nation specific modules (AI accelerated), on the build order in the research pack.
- **Hosting:** Canadian, Nation controllable (Web Networks is the hosting foundation), a service line
  at $2,500 per year per Nation.
- **Delivery and support:** migration and implementation per Nation, then managed support. Repeatable
  provisioning, the branding pipeline, and the SaaS layer are the productization work in `tech-lane-template.md`.
- **The AI Operator model.** Matthew gathers a Nation's real needs and trains the Nation's own staff
  to operate the tools; Russell and the AI tooling build. Lean delivery, and itself a sellable service
  ("AI Operatives training AI Operators"). See `../../partnership/working-relationship.md`.

## 11. Funding to build the product (non dilutive first)

- **Aboriginal Entrepreneurship Program (AEP):** non repayable, up to $99,999 (individual) or
  $250,000 (community owned), via NACCA and the Indigenous Financial Institutions. Best fit to build.
- **FedNor (Northern Ontario Development Program):** up to about 33 percent of admissible expenses,
  ICT named, strong fit since Sagamok is Northern Ontario.
- **Indigenous Financial Institutions (about 59 in the NACCA network):** developmental lending.
- **Raven Indigenous Capital Partners:** Indigenous focused venture capital if an equity path is chosen.

## 12. Unit economics per Nation (modeled)

- Year one: about $12,000 migration plus a partial year of platform and initial modules, offset in the
  Nation's eyes by the Microsoft spend it stops or stops growing.
- At maturity: about $23,825 per year recurring, rising about 5 percent a year (NRR 1.05) before new
  module adoption.
- A Nation that takes core plus three or four modules, hosting, and support sits at or above the
  blended average; a core only Nation (platform, AI, hosting) sits near $14,000 to $21,000.

## 13. INGEN (the corporate lane, described, not yet modeled)

The same engine pointed outward at companies that must engage Nations: find and verify Indigenous
suppliers, meet the Duty to Consult, report ESG and reconciliation. Illustrative only at about
$50,000 per corporate client per year, many times a single band's budget, so even a modest foothold
can outgrow the entire First Nations lane. Guardrail: built on public and aggregate information and
FNPI's network, never a Nation's private data, framed as helping money and proper engagement flow to
Nations. To be sized with Matthew and added to the model.

## 14. Key assumptions, flags, and risks

- **Modeled inputs to confirm:** all module prices and attach rates, the $12,000 migration fee, average
  band employees (65) for payroll, and the blended ARPA. Turn them as real quotes land.
- **Microsoft spend figures** ($12.50 to $22 per user per month) are commercial list ranges; confirm
  against a real band's actual bill (Sheguiandah or Sagamok) to make the displacement case concrete.
- **Build risk:** integrating the open source suite cleanly is the main engineering risk; co
  authoring is handled by the open source components, and email is de risked because Web Networks
  already runs mail (build on or rebuild with them rather than from zero).
- **Penetration risk:** the SOM scenarios assume Xyntax to OneFeather class adoption over five years,
  which depends on sales execution (Matthew's lane).
- **Competitive risk:** Tsen'awt, and any cloud move by OneFeather. Differentiator is the procurement
  vehicle plus owning the technology plus the reference build.
- **Conflict of interest:** the Sheguiandah relationship runs through Matthew, who is both a Sheguiandah
  employee and FNPI president. Flagged and parked; manage with disclosure and recusal before the FNPI
  contract (`../../partnership/working-relationship.md`).
- **Scope:** technology lane only. Sourcing and Protection are separate.

## 15. What turns this into the business plan

Wrap this model with the company and ownership story (FNPI plus Russell's role, still open), the team,
a written go to market and sales plan, the build and migration roadmap with milestones, the funding ask
tied to section 11, and INGEN sized into the numbers. The next concrete steps: lock the offerings
(prune `fn-offerings-explorer.html`), confirm the modeled prices and attach rates with Matthew, and pull
a real band's Microsoft bill to ground the displacement case. Then the projection becomes the plan's financials.

## Sources

- FNPI revenue model: `FNPI-market-model.xlsx` (this folder), on ISC Indian Register 2024 open data.
- Market, procurement, modules, funding, OCAP: `market-procurement-research.md` (this folder), sources cited inline there.
- Microsoft nonprofit eligibility (governments excluded): https://www.microsoft.com/en-us/nonprofits/eligibility and https://learn.microsoft.com/en-us/industry/nonprofit/microsoft-for-nonprofits/eligibility
- Microsoft nonprofit grant changes (July 2025): https://support.techsoup.org/hc/en-us/articles/37040124982811-Donated-Microsoft-365-Business-Premium-and-Office-365-E1-Discontinued
- The repeatable delivery pattern: `tech-lane-template.md`. The pitch narrative: `FNPI-pitch-Matt.pptx` / `.pdf`.
