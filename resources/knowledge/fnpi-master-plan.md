# FNPI master plan: from business model to live website

**Prepared:** 2026-06-04 · **For:** Russell and Matthew · **Owner of decisions:** Russell
(supplies material, runs Claude Code) and Matthew (FNPI owner, operational facts).
No em dashes. Public claims get sourced. Figures are never invented; placeholders stay
placeholders until Matthew or a public source confirms them.

## The one line

Build FNPI a business model and a business plan first, then a new website on the Waaseyaa
framework that replaces the GoDaddy site at fnprocure.ca. The website is the last step, not
the first. It is the public face of a business plan we will have already written.

## Scope locked (2026-06-04)

- **FNPI is the umbrella.** One 100% First Nations owned company, three lanes under one
  procurement qualification. NorthOps is the technology lane inside FNPI, not a separate brand.
- **The new Waaseyaa site replaces fnprocure.ca.** It becomes the primary FNPI site and is
  eventually pointed at the same domain. The current GoDaddy builder site is what we retire.
- **Business model is built from a mix:** Matthew's existing material (revenue, Faraday
  inventory economics, pipeline, contracts) plus market research to fill the gaps.
- **Deploy is parked.** We build the site locally on Waaseyaa. GoDaddy hosting, DNS, and the
  domain cutover are a later phase. We do not touch them now.

### Focus update (2026-06-04)

- **FNPI is inactive and being revived.** Russell is joining Matthew to revive and expand it.
  FNPI is the vehicle; the moat is its Indigenous ownership plus procurement qualification plus
  operating history since 2017.
- **The current focus is the Technology and Software lane.** That is the growth engine. Its
  repeatable pattern is written up separately in `tech-lane-template.md`, distilled from the
  Sheguiandah reference build.
- **Brand architecture is settled.** FNPI is the company. Anokii is the product for Nations.
  INGEN is the product for companies. Waaseyaa is the engine. Co-Intelligence is Anokii's in
  product AI.
- **Two different websites, do not conflate them.** (1) The FNPI company site that replaces
  fnprocure.ca, the storefront that sells the lanes. (2) The Anokii instances, each Nation's
  own site and workspace (Sheguiandah is the first reference build). This plan's website track
  is the FNPI company site; the Anokii instances are the product, covered in the template.
- **A pitch is already built and sent to Matthew**, who replied warmly. So Phase 1 is not
  starting cold; much of the model and story exists (see Asset inventory).

## How the pieces nest

```
Business model        (what FNPI sells, to whom, how it makes money)
   └─ Business plan    (the full document the venture runs on, funders read)
        └─ Website      (the public expression of the plan, built on Waaseyaa)
             └─ Deploy   (parked: GoDaddy / PHP host, domain cutover)
```

The website sits at the bottom because it should say what the business plan decides. We do
the thinking once, then express it.

## Who does what

- **Russell.** Decides, supplies material, runs Claude Code for the build, is the source of
  truth. Corrects anything confident but wrong.
- **Matthew Owl.** FNPI owner. Supplies operational facts no one else has (real revenue,
  Faraday unit economics, supplier network, live pipeline, contract history). Endorses the
  narrative. Keeps the defense and drone lane private and off public materials.
- **This Cowork session.** Planning artifacts, market research, business model shaping with
  Russell, the business plan draft, website content and sitemap, and the Claude Code build
  prompts. No repo access here.
- **Claude Code.** Drives the Waaseyaa repo and builds the site locally from the content and
  prompts produced here. Same working model proven on oiatc.ca.

## Phases

### Phase 0. Foundation and alignment (now)
- **Goal:** agree the plan and gather inputs.
- **Steps:** lock scope (done), audit fnprocure.ca (done), inventory the prototype assets
  (done), collect Matthew's existing material, confirm the entity and ownership facts.
- **Output:** this plan, plus a short inputs checklist for Matthew.
- **Owner:** Russell and Cowork.

### Phase 1. Business model
- **Goal:** decide what FNPI sells, to whom, and how each lane makes money.
- **Steps:** map the three lanes to concrete offerings and revenue types (one-off sourcing
  margin, Faraday product sales, recurring software, services). Define target customers per
  lane. Research the market: the federal 5 percent Indigenous procurement target, market size,
  the comparable players (VigilAInt / Cancom), and the tailwind from Canada's new AI strategy
  for the technology lane. Pressure test the moat claim (one qualification, three lanes).
- **Output:** a business model document, likely a one page model canvas plus a revenue model
  per lane.
- **Owner:** Cowork drafts, Russell and Matthew decide.

### Phase 2. Business plan
- **Goal:** the full document the venture runs on and funders or partners can read.
- **Steps:** build from the model and research. Sections: summary, company and ownership,
  market and opportunity, the three lanes, go to market, operations, team, financials and
  projections, roadmap and milestones, risks.
- **Output:** the FNPI business plan (working draft, then a clean shareable version).
- **Owner:** Cowork drafts, Russell and Matthew decide. Financials wait on Matthew's numbers.

### Phase 3. Website definition
- **Goal:** decide exactly what the site says and contains before anything is built.
- **Steps:** derive the sitemap and page content from the business plan and the existing
  narrative. Map the prototype (homepage concept, lanes explorer, offerings explorer) onto
  Waaseyaa pages. Decide the commerce question (see Open decisions). Write final page copy.
  Produce the Claude Code build prompts as fenced, one click copy blocks.
- **Output:** sitemap, page content, and the Claude Code prompt set.
- **Owner:** Cowork drafts, Russell approves.

### Phase 4. Website build, local on Waaseyaa
- **Goal:** a working site running locally that Russell can see and refine.
- **Steps:** Russell runs the Claude Code prompts against the Waaseyaa repo. Claude Code builds
  pages, layout, and content. Iterate on look and copy. Browser review.
- **Output:** the new FNPI site running locally.
- **Owner:** Russell drives Claude Code; Cowork supplies prompts, content, and fixes.

### Phase 5. Deploy (parked)
- **Goal:** the new site live at fnprocure.ca.
- **Steps:** decide the host (PHP host preferred over a builder), investigate the GoDaddy
  account, plan the DNS and domain cutover, migrate or rebuild the store, redirect old URLs.
- **Output:** live site. **Not now.** Listed so it is not forgotten.
- **Owner:** Russell, with Cowork producing a deploy and migration plan when we get there.

## What we are replacing: fnprocure.ca today

The live site is built on **GoDaddy Website Builder 8.0**. Its content:

- Tagline **Service and Sourcing Solutions**, a get a free quote call to action.
- About FNPI, a Vision (trusted leader in Indigenous engagement, national and economic
  sovereignty), and a Mission (sourcing and services for economic self determination).
- Three service blurbs: Trade Mission Services, Experience You Can Trust, Customized Solutions.
- A **Privacy and Data Protection, Faraday Phone and Utility Cases** section.
- A Sourcing and Sampling section with a YouTube video.
- A testimonials placeholder, a contact and quote form, address (91 River Rd, Sagamok
  Anishnawbek), info@fnprocure.ca, and a Facebook link.
- **A GoDaddy store:** cart, shop, bookings, orders, and account sign in.

**The store is the one piece a plain content site does not replace by itself.** The Faraday
cases are sold through the GoDaddy shop, and there are bookings and orders. A Waaseyaa PHP
site handles all the content and forms cleanly, but the commerce needs a decision (see Open
decisions). Flagging it now so it does not surprise us at cutover.

## Asset inventory: what already exists

In `business/northops-fnpi-venture/`:

- `fnpi-narrative.md`. The positioning spine. The three lanes, the moat, the honesty line,
  the public posture (defense and drones stay private). This is the source for site messaging.
- `fnpi-homepage-concept.html`. A polished concept homepage, dark hero, three lane layout.
  Matthew endorsed the concept. The visual reference for the build.
- `fnpi-lanes-explorer.html`, `fn-offerings-explorer.html`. Interactive supporting pages.
- `anokii-secure-ops/`. The FNPI security demo prototype (steel and amber, Operations and
  Procurement modules). Private posture, not public site material.
- `research-brief.md`. FNPI profile, the early landscape read, the NorthOps x FNPI thesis.
- `market-procurement-research.md`. FN software market, incumbents and pricing, the 5 percent
  mechanics, the Section 87 payroll wedge, the department module map, OCAP. Folds into Phase 1.
- `tech-lane-template.md`. **The repeatable Technology lane pattern from Sheguiandah.** Stack,
  delivery playbook, productization gaps, replication economics, and the INGEN extension.
- `FNPI-pitch-Matt.pptx` and `.pdf`. The 14 slide technology lane pitch sent to Matthew. Builds
  the FN lane, reveals INGEN, the combined money, proof, partnership, ask, sources.
- `FNPI-market-model.xlsx`. The itemized revenue model on 2024 ISC data (about 580 serviceable
  Nations, about $22K blended per Nation, about $13M serviceable market, about $2.5M by year 5).
  INGEN is deck level only, not yet in the model.
- `NorthOps-FNPI-onepager.pdf`. The earlier one page summary.
- `fn-offerings-explorer.html`. The interactive offerings catalog for Russell to prune
  (Keep / Maybe / Cut). Pruning it gates the offerings document and the business model.
- `fn-communities.csv`. The 637 First Nation registry with band numbers, the countable market.

## Research queue (Phase 1 feeds this)

- The federal 5 percent Indigenous procurement target: current status, rules, and how a
  qualified vendor like FNPI actually wins under it.
- Indigenous procurement market size and the buyers (federal, provincial, corporate ESG).
- The comparable players, VigilAInt and Cancom, and where FNPI differentiates.
- The Faraday and data protection product market.
- The tailwind: Canada's new AI strategy names Indigenous AI, data sovereignty, and Indigenous
  procurement. Useful for the technology lane positioning. Keep this commercial and separate
  from OIATC, which stays nonpartisan and is not part of this venture.

## Open decisions (need Russell or Matthew)

1. **Primary tagline.** Current site leads with Service and Sourcing Solutions. The narrative
   offers evolutions (for example, Sourcing, technology, security, built on sovereignty).
2. **Commerce on the new site.** Keep the GoDaddy store as is and link to it, rebuild the
   Faraday shop inside Waaseyaa, or use a lightweight checkout (for example a hosted cart).
   This drives how much the Waaseyaa build has to do.
3. **Entity and ownership of the technology lane.** FNPI is the umbrella, but how NorthOps sits
   inside it (contract, subsidiary, revenue split) shapes the business plan financials.
4. **How loud sovereignty is** versus procurement in the public story. Internal lean from the
   narrative: procurement qualifies us, sovereignty sells us.

## Running questions log

I keep questions here and ask them one at a time rather than dumping a list. Answered items
move to Scope locked or Open decisions.

- (answered) Who is this for? FNPI umbrella, NorthOps as technology lane.
- (answered) Site relationship to fnprocure.ca? Replace it.
- (answered) Business model inputs? Mix of Matthew's material and research.
- (open, queued) What material does Matthew already have to share (revenue, Faraday economics,
  pipeline, contracts)? Needed to start Phase 1 financials honestly.
- (open, queued) Tagline, commerce, entity structure, sovereignty volume (the four above).

## Immediate next step

Focus is the Technology and Software lane. The story and a modeled revenue forecast already
exist (the pitch and the market model). The next unblock toward the business model is pruning
the offerings catalog (`fn-offerings-explorer.html`) into a settled offerings list, then turning
that plus the template into the business plan. The financials move from modeled to grounded as
Matthew's real numbers come in. I will ask for those one at a time.
