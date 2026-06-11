<?php

declare(strict_types=1);

namespace App\Pages;

/**
 * The canonical content of the five public marketing pages, as ordered content
 * blocks (Anokii Pages, increment 1).
 *
 * This is the single source of truth the migration seeds into `page` entities
 * (block order per page is pinned by PageStructureTest). Each entry is
 * keyed by route path and carries the page's <title>, optional SEO meta, optional
 * per-page head CSS, and an ordered list of blocks. Every block's `type` names a
 * partial in templates/blocks/; its remaining keys are that block type's content.
 *
 * Text is stored exactly as it appears in the source markup, including HTML
 * entities (&amp;, &middot;, &nbsp;, &rarr;), because the partials emit content
 * with |raw, preserving the copy exactly as authored. `meta_description` /
 * `head_styles` are null when the page does not override the layout default.
 */
final class PageSeedData
{
    /**
     * @return array<string, array{
     *     title: string,
     *     meta_description: ?string,
     *     meta_robots: ?string,
     *     head_styles: ?string,
     *     blocks: list<array<string, mixed>>
     * }>
     */
    public static function all(): array
    {
        $pages = [
            '/' => [
                'title' => 'First Nations Procurement Inc., Service &amp; Sourcing Solutions',
                'meta_description' => null,
                'meta_robots' => null,
                'head_styles' => null,
                'blocks' => [
                    [
                        'type' => 'hero',
                        'eyebrow' => '100% First Nations-owned &nbsp;&middot;&nbsp; Since 2017',
                        'pre' => 'We can provide a solution for your operational needs.',
                        'h1' => 'Service &amp; Sourcing Solutions',
                        'oneline' => 'Indigenous-owned sourcing, technology, and protection.',
                        'cta' => [
                            'primary' => ['label' => 'Get a free quote', 'href' => '/contact'],
                            'secondary' => ['label' => 'Explore our solutions', 'href' => '/technology'],
                        ],
                        'trust' => [
                            ['value' => '100%', 'label' => 'First Nations-owned'],
                            ['value' => 'Since 2017', 'label' => 'operating'],
                            ['value' => 'Sagamok', 'label' => 'Anishnawbek'],
                        ],
                    ],
                    [
                        'type' => 'feature_lanes',
                        'sec_h' => 'What we do',
                        'sec_t' => 'Three lanes, one qualification',
                        'sec_sub' => 'Everything we offer runs on the same foundation: a 100% First Nations-owned company qualified to deliver through Indigenous procurement channels.',
                        'lane1' => [
                            'tag' => 'Running today',
                            'h3' => 'Sourcing &amp; Services',
                            'body' => 'Indigenous-content sourcing, sampling, and trade missions. We find, vet, and deliver the goods and services your operation needs, backed by a Faraday data-protection product line in stock.',
                        ],
                        'lane2' => [
                            'href' => '/technology',
                            'tag' => 'Building',
                            'h3' => 'Technology &amp; Software',
                            'body' => 'Sovereign, Nation-owned software: websites and member portals, a secure workspace, and department tools, hosted in Canada and governed by the Nation. Data that stays yours.',
                            'go' => 'Explore the platform &rarr;',
                        ],
                        'lane3' => [
                            'tag' => 'Shipping now',
                            'h3' => 'Privacy &amp; Protection',
                            'body' => 'Counter-surveillance, data protection, and protective gear, led by our Faraday line: enclosures that shield devices and data from interception. Real products, already shipping.',
                        ],
                    ],
                    [
                        'type' => 'band_proof',
                        'sec_h' => 'Built on sovereignty',
                        'h2' => 'Procurement is our qualification. Sovereignty is our purpose.',
                        'body' => 'FNPI exists to strengthen Indigenous economic self-determination. Our Indigenous ownership is not a label, it is the qualification that lets governments and corporations buy from us and meet their Indigenous procurement commitments, across sourcing, technology, and protection alike.',
                        'cta' => ['label' => 'See the sovereign platform', 'href' => '/technology'],
                        'proof' => [
                            ['value' => '100%', 'label' => 'First Nations-owned and controlled'],
                            ['value' => '5%', 'label' => 'Federal Indigenous procurement target we qualify for'],
                            ['value' => '2017', 'label' => 'Operating and delivering since'],
                            ['value' => '10+', 'label' => 'Years of supply-chain experience'],
                        ],
                    ],
                    [
                        'type' => 'photo_strip',
                        'sec_h' => 'Track record',
                        'h2' => 'Ten years in the room.',
                        'photos' => [
                            [
                                'src' => '/img/tmx-market-open.jpg',
                                'alt' => 'Matthew Owl standing at the Toronto Stock Exchange market-open board.',
                                'caption' => 'Matthew Owl, FNPI President, at the TMX market open for National Indigenous History Month.',
                            ],
                            [
                                'src' => '/img/xian-trade-mission-2011.jpg',
                                'alt' => "Two delegates walking on the Xi'an city wall.",
                                'caption' => 'Trade mission, Xi&#039;an, China, 2011. The supply-chain relationships predate the company.',
                            ],
                        ],
                    ],
                    [
                        'type' => 'faraday_feature',
                        'panel_label' => 'Grounded on your files',
                        'image' => '/img/anokii-cointelligence.jpg',
                        'image_fit' => 'natural',
                        'image_alt' => 'A Co-Intelligence chat in the Anokii workspace: the question "What does our defence page say about data jurisdiction?" answered with a grounded reply that quotes the defence page and cites it with source chips.',
                        'sec_h' => 'Sovereign AI',
                        'sec_t' => 'AI that lives where your data lives.',
                        'sec_sub' => 'Co-Intelligence is the assistant inside Anokii, grounded on your own records and running on Canadian-controlled infrastructure. Answers come from your files, and your files go nowhere.',
                        'cta' => ['label' => 'See the platform', 'href' => '/technology'],
                    ],
                    [
                        'type' => 'faraday_feature',
                        'panel_label' => 'Faraday Protection',
                        'sec_h' => 'In stock and shipping',
                        'sec_t' => 'Faraday cases',
                        'sec_sub' => 'Electromagnetic-shielding enclosures that block cellular, WiFi, GPS, and radio signals to protect devices and data from interception. A tangible counter-surveillance and privacy product, available now on request.',
                        'cta' => ['label' => 'Request Faraday cases', 'href' => '/contact'],
                    ],
                    [
                        'type' => 'vision_mission',
                        'sec_h' => 'About First Nations Procurement Inc.',
                        'sec_t' => 'A trusted Indigenous partner',
                        'items' => [
                            ['lbl' => 'Our Vision', 'body' => 'To be a trusted leader in Indigenous engagement and strengthen national sovereignty while fostering economic sovereignty.'],
                            ['lbl' => 'Our Mission', 'body' => 'To provide exceptional sourcing and services solutions that strengthen economic self-determination, building trusted industry relationships and a legacy of strength and opportunity for generations to come.'],
                        ],
                    ],
                    [
                        'type' => 'cta_band_center',
                        'sec_h' => 'Get in touch',
                        'sec_t' => 'Tell us what your Nation needs',
                        'sec_sub' => 'From a single sourcing request to a Nation-wide platform, start with a free quote. We work with First Nations Councils and administrators, and with funders and partners who want to support the build.',
                        'cta_primary' => ['label' => 'Request a quote', 'href' => '/contact'],
                        'cta_secondary' => ['label' => 'Explore the platform', 'href' => '/technology'],
                    ],
                ],
            ],

            '/technology' => [
                'title' => "Technology: Your Nation's platform, owned not rented · FNPI",
                'meta_description' => 'Anokii is the sovereign workspace for First Nations: website, member portal, drive, and AI in one system the Nation owns, hosted in Canada, governed by Council.',
                'meta_robots' => null,
                'head_styles' => <<<'CSS'
                      .modgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
                      .mod{background:#fff;border:1px solid var(--line);border-radius:4px;padding:18px 18px 20px}
                      .mod h4{margin:0 0 6px;font-size:16px;color:var(--ink)}
                      .mod p{margin:0;font-size:13.5px;color:var(--muted)}
                      .mod .tag{font-family:"Oswald",sans-serif;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.1em;color:#7a4e1d;background:#f3e9da;border-radius:2px;padding:2px 7px;display:inline-block;margin-bottom:8px}
                      .checks{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:1fr 1fr;gap:12px 28px}
                      .checks li{padding-left:26px;position:relative;color:var(--muted)}
                      .checks li b{color:var(--ink)}
                      .checks li::before{content:"";position:absolute;left:0;top:7px;width:12px;height:12px;border:2px solid var(--cyan);border-radius:2px}
                      @media(max-width:880px){.modgrid{grid-template-columns:1fr 1fr}.checks{grid-template-columns:1fr}}
                    CSS . "\n",
                'blocks' => [
                    [
                        'type' => 'hero',
                        'eyebrow' => 'Sovereign technology for Nations',
                        'h1' => "Your Nation's platform. Owned, not rented.",
                        'oneline' => 'One connected system the Nation owns, hosted in Canada, governed by Council, with AI built in.',
                        'cta' => [
                            'primary' => ['label' => 'Request a quote', 'href' => '/contact'],
                            'secondary' => ['label' => 'See how it works', 'href' => '/how-it-works'],
                        ],
                    ],
                    [
                        'type' => 'text_intro',
                        'sec_h' => 'The problem: own vs rent',
                        'paragraphs' => [
                            'A Nation today runs on tools it rents and will never own: Microsoft 365 seats renewed year after year, files sitting in a cloud reachable under US law, a website no one on staff can edit, and the real work scattered across spreadsheets that do not talk to each other. None of it belongs to the Nation.',
                            'Anokii changes the terms. Instead of renting your tools forever, the Nation runs on one platform it owns and controls, with the spending that once renewed a licence now building something the Nation keeps.',
                        ],
                    ],
                    [
                        'type' => 'module_grid',
                        'sec_h' => 'Anokii, the sovereign workspace',
                        'h2' => 'One system. One login. One record across departments.',
                        'sec_sub' => 'Anokii is an entity-first, AI-native platform. Instead of a dozen rented apps that do not talk to each other, the Nation runs on one workspace it controls.',
                        'mods' => [
                            ['h4' => 'Website', 'body' => 'A fast, branded public site with a real editable backend.'],
                            ['h4' => 'Member Portal', 'body' => 'Secure services and information for members, online.'],
                            ['h4' => 'Drive', 'body' => "The Nation's files and documents, stored in Canada."],
                            ['h4' => 'Co-Intelligence', 'body' => 'A built-in AI assistant grounded on your own files.'],
                            ['h4' => 'Data Rooms', 'body' => 'Controlled spaces for sensitive negotiations and records.'],
                            ['h4' => 'Workspaces', 'body' => 'Department workspaces that share one source of truth.'],
                            ['h4' => 'Governance', 'body' => 'Council records, motions, and decisions in one place.'],
                            ['h4' => 'Vault', 'body' => 'Encrypted storage for the records that matter most.'],
                        ],
                    ],
                    [
                        'type' => 'photo_strip',
                        'photos' => [
                            [
                                'src' => '/img/anokii-dashboard.jpg',
                                'alt' => 'The Anokii workspace dashboard: a greeting banner and a grid of module cards including Identity Workspace, Drive, Documents, Co-Intelligence, Pages, Inbox, and Analytics, with Data Rooms, Workspaces, Portal, Vault, and Governance marked coming soon.',
                                'caption' => 'FNPI&#039;s own Anokii workspace. The platform we deploy is the platform we run.',
                            ],
                        ],
                    ],
                    [
                        'type' => 'faraday_showcase',
                        'sec_h' => 'AI is core, not an add-on',
                        'sec_t' => 'Co-Intelligence',
                        'sec_sub' => "Anokii's assistant is grounded on the Nation's own files, so answers come from your records, not the open internet. Meeting-minutes transcription runs locally, so the audio and the minutes stay in Canada. The AI is part of the platform from day one, helping a small office do the work of a much larger one.",
                        'panel_label' => 'Grounded on your files',
                    ],
                    [
                        'type' => 'checklist',
                        'sec_h' => 'Sovereignty, concretely',
                        'sec_t' => 'Your data stays yours, and in Canada.',
                        'sec_sub' => 'Sovereignty is not a slogan here. It is how the platform is built.',
                        'items' => [
                            '<b>Canadian incorporation and data residency.</b> Your data lives in Canada, under Canadian law.',
                            "<b>Per-Nation isolation.</b> Each Nation's data is its own; never pooled, never resold.",
                            '<b>Member access and export rights.</b> The Nation can get its data out, any time.',
                            '<b>OCAP alignment.</b> Ownership, Control, Access, and Possession stay with the Nation.',
                        ],
                    ],
                    [
                        'type' => 'department_list',
                        'sec_h' => 'Department modules',
                        'h2' => 'What you can grow into.',
                        'sec_sub' => 'Added one at a time, each owned by the Nation. The platform and first modules are what we are building now. The rest is what you grow into as trust builds, not a suite that ships all at once.',
                        'items' => [
                            'Housing and assets',
                            'Income assistance',
                            'Finance and ISC reporting',
                            'Membership and registry',
                            'Governance and BCRs',
                            "Health intake / Jordan's Principle",
                            'Lands',
                            'Emergency management',
                            'HR and payroll with Section 87',
                        ],
                    ],
                    [
                        'type' => 'text_center',
                        'sec_h' => 'Hosted in Canada',
                        'sec_t' => 'Sovereign software, on a path to sovereign infrastructure.',
                        'sec_sub' => 'Today the platform is hosted on Web Networks, a Canadian, Indigenous-aligned provider. The long-term goal is First Nations-owned hosting, so the infrastructure becomes as sovereign as the software running on it.',
                    ],
                    [
                        'type' => 'hero_cta',
                        'h2' => 'Own your Nation\'s platform.',
                        'cta_primary' => ['label' => 'Request a quote', 'href' => '/contact'],
                        'cta_secondary' => ['label' => 'See how it works', 'href' => '/how-it-works'],
                    ],
                ],
            ],

            '/how-it-works' => [
                'title' => "How it works: Start where it shows, grow as you trust it · FNPI",
                'meta_description' => 'A land-and-expand playbook for Nations: start with a website and member portal, prove sovereignty, expand into department modules, and own the full stack. Your own people run it.',
                'meta_robots' => null,
                'head_styles' => <<<'CSS'
                      .ladder{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;counter-reset:step}
                      .rung{background:#fff;border:1px solid var(--line);border-radius:4px;padding:22px 20px;position:relative}
                      .rung .n{font-family:"Anton",sans-serif;font-size:28px;color:var(--cyan2);line-height:1}
                      .rung h3{margin:10px 0 8px;font-size:22px;color:var(--ink)}
                      .rung p{margin:0;font-size:14px;color:var(--muted)}
                      .seq{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0}
                      .seq li{display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1px solid var(--line);font-size:16px}
                      .seq li:last-child{border-bottom:none}
                      .seq .k{font-family:"Oswald",sans-serif;font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#0f7488;background:#e3f4f8;border-radius:2px;padding:4px 10px;white-space:nowrap}
                      .seq .last .k{color:#7a4e1d;background:#f3e9da}
                      @media(max-width:880px){.ladder{grid-template-columns:1fr 1fr}}
                    CSS . "\n",
                'blocks' => [
                    [
                        'type' => 'hero',
                        'eyebrow' => 'The playbook',
                        'h1' => 'Start where it shows. Grow as you trust it.',
                        'oneline' => 'We land fast with what a Nation needs first, prove sovereignty is real, then expand one step at a time, on your timeline.',
                        'cta' => [
                            'primary' => ['label' => "Let's scope your Nation", 'href' => '/contact'],
                            'secondary' => ['label' => 'See the platform', 'href' => '/technology'],
                        ],
                    ],
                    [
                        'type' => 'stage_ladder',
                        'sec_h' => 'The four stages',
                        'sec_t' => 'A ladder, not a leap.',
                        'sec_sub' => 'Each stage stands on its own and de-risks the next. You never have to bet everything at once.',
                        'rungs' => [
                            ['n' => '01', 'h3' => 'Land', 'body' => 'A fast, branded website and a working member portal. Visible value, in weeks.'],
                            ['n' => '02', 'h3' => 'Prove', 'body' => 'Sovereign hosting in Canada, OCAP alignment, and Council governance. Sovereignty you can show.'],
                            ['n' => '03', 'h3' => 'Expand', 'body' => 'Department modules, added one at a time. Each one owned by the Nation, each one compounding.'],
                            ['n' => '04', 'h3' => 'Own', 'body' => 'The full stack, on infrastructure you control. The rented tools are gone.'],
                        ],
                    ],
                    [
                        'type' => 'migration_sequence',
                        'sec_h' => 'The migration, plainly',
                        'h2' => 'We move you off the rented stack in a sequence that lands fast and de-risks each step.',
                        'sec_sub' => 'No big-bang cutover, no lift-and-pray. A clear order, with email moved last so nothing critical is ever in the air.',
                        'items' => [
                            ['k' => 'First', 'text' => 'Website onto the platform, with a real editable backend.'],
                            ['k' => 'Then', 'text' => 'Member portal, so members get services online.'],
                            ['k' => 'Then', 'text' => 'Department data, off spreadsheets and legacy apps.'],
                            ['k' => 'Then', 'text' => 'Files, documents, and collaboration in one place.'],
                            ['k' => 'Email last', 'text' => 'Moved only once everything else is solid.', 'last' => true],
                        ],
                    ],
                    [
                        'type' => 'text_center',
                        'sec_h' => 'Your people run it',
                        'sec_t' => 'The AI Operator',
                        'sec_sub' => "We do not parachute in and leave you dependent. We train a trusted person already in your office, amplified by the platform's built-in AI, so the day-to-day capability lives in the community. The money, the control, and the know-how stay with the Nation. It is OCAP aligned and non-extractive by design: AI Operatives training AI Operators.",
                    ],
                    [
                        'type' => 'faraday_showcase',
                        'sec_h' => 'Procurement is the enabler',
                        'sec_t' => 'Funded through the channels you already qualify for.',
                        'sec_sub' => 'Because FNPI is itself a 100% First Nations-owned procurement vehicle, on both the ISC and CCAB registries, a Nation can buy through the federal 5% Indigenous procurement set-aside and ISC funding streams. Sovereignty is why you want it; procurement is how you pay for it.',
                        'panel_label' => '5% set-aside &middot; ISC funding',
                    ],
                    [
                        'type' => 'hero_cta',
                        'h2' => "Let's scope your Nation.",
                        'cta_primary' => ['label' => 'Request a quote', 'href' => '/contact'],
                        'cta_secondary' => ['label' => 'See the platform', 'href' => '/technology'],
                    ],
                ],
            ],

            '/defence' => [
                'title' => 'Defence &amp; Security: First Nations Procurement Inc.',
                'meta_description' => "FNPI brings 100% First Nations-owned capability to Canada's defence and security supply chain: sovereign AI and digital systems, personnel protection and materiel, and privacy-first sensor systems.",
                'meta_robots' => null,
                'head_styles' => <<<'CSS'
                      .modgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
                      .mod{background:#fff;border:1px solid var(--line);border-radius:4px;padding:18px 18px 20px}
                      .mod h4{margin:0 0 6px;font-size:16px;color:var(--ink)}
                      .mod p{margin:0;font-size:13.5px;color:var(--muted)}
                      .checks{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:1fr 1fr;gap:12px 28px}
                      .checks li{padding-left:26px;position:relative;color:var(--muted)}
                      .checks li b{color:var(--ink)}
                      .checks li::before{content:"";position:absolute;left:0;top:7px;width:12px;height:12px;border:2px solid var(--cyan);border-radius:2px}
                      @media(max-width:880px){.modgrid{grid-template-columns:1fr 1fr}.checks{grid-template-columns:1fr}}
                    CSS . "\n",
                'blocks' => [
                    [
                        'type' => 'hero',
                        'eyebrow' => 'Defence &amp; Security &nbsp;&middot;&nbsp; 100% First Nations-owned',
                        'h1' => "An operator's standing, not an observer's.",
                        'oneline' => "FNPI brings First Nations-owned capability to Canada's defence and security supply chain: sovereign AI and digital systems, personnel protection and materiel, and sensor systems built privacy-first.",
                        'cta' => [
                            'primary' => ['label' => 'Start the conversation', 'href' => '/contact'],
                            'secondary' => ['label' => 'See the platform', 'href' => '/technology'],
                        ],
                    ],
                    [
                        'type' => 'text_intro',
                        'sec_h' => 'Who we are',
                        'paragraphs' => [
                            "First Nations Procurement Inc. is a 100% First Nations-owned technology and procurement company operating from Sagamok Anishnawbek First Nation since 2017, registered on the Indigenous Services Canada Business Directory and CCAB certified. Our defence and security capability spans four areas, and in each one our standing is an operator's: we build it, run it, or supply it ourselves.",
                        ],
                    ],
                    [
                        'type' => 'module_grid',
                        'sec_h' => 'Capability',
                        'h2' => 'Four areas, an operator in each.',
                        'sec_sub' => 'We build it, run it, or supply it ourselves.',
                        'mods' => [
                            ['h4' => 'Artificial intelligence', 'body' => 'First Nations-created AI on Canadian-controlled infrastructure. Our sovereign AI workspace platform is built with data residency, auditability, and Indigenous data governance (the OCAP principles) designed in from the start, not added later.'],
                            ['h4' => 'Digital systems &amp; cyber', 'body' => 'Sovereign digital capability depends on who owns the infrastructure and whose law reaches the data. We build and run systems on Canadian-controlled infrastructure, with data jurisdiction, dual-use AI governance, and supply chain trust treated as design constraints.'],
                            ['h4' => 'Personnel protection &amp; materiel', 'body' => 'Through our partner network, direct access to body armour, helmets, armoured vehicles, carriers, and sites suitable for defence-related use.'],
                            ['h4' => 'Sensors', 'body' => 'Edge AI security analytics built privacy-first, with no facial recognition by design. Sensor and camera feeds are processed on-site, so data stays in the community and under its jurisdiction.'],
                        ],
                    ],
                    [
                        'type' => 'checklist',
                        'sec_h' => 'Both sides of the chain',
                        'sec_t' => 'The view from both sides of the chain',
                        'sec_sub' => 'Capability delivery in Canada runs through chains that Indigenous and small firms experience from the far end. We know that system from both sides of our business:',
                        'items' => [
                            '<b>Materiel:</b> supply access depends on distribution relationships, certification, and the working capital to hold inventory, exactly where small and Indigenous firms drop out of the chain.',
                            '<b>Digital:</b> sovereign AI and cyber capability depends on infrastructure ownership, data jurisdiction, and a skilled workforce pipeline; for remote and First Nations communities, workforce is inseparable from training capacity and connectivity.',
                            '<b>Policy:</b> Indigenous procurement commitments, ITB obligations, and federal sovereignty requirements shape who can participate, and at what tier.',
                        ],
                    ],
                    [
                        'type' => 'text_center',
                        'sec_h' => 'Sovereignty',
                        'sec_t' => 'Sovereignty is not a talking point here.',
                        'sec_sub' => 'It is where our files live, whose law reaches them, and who owns the infrastructure they run on. That is the capability we build, and the standing we bring.',
                    ],
                    [
                        'type' => 'hero_cta',
                        'h2' => 'Defence and security work starts with a conversation.',
                        'cta_primary' => ['label' => 'Contact us', 'href' => '/contact'],
                        'cta_secondary' => ['label' => 'See the platform', 'href' => '/technology'],
                    ],
                ],
            ],

            '/faraday' => [
                'title' => 'Faraday cases: signal-blocking protection, in stock · FNPI',
                'meta_description' => 'Faraday enclosures that block cellular, WiFi, GPS, and radio signals to protect devices and data from interception. In stock and shipping from Canada. 100% First Nations-owned supplier.',
                'meta_robots' => null,
                'head_styles' => null,
                'blocks' => [
                    [
                        'type' => 'hero',
                        'eyebrow' => 'Faraday Protection &nbsp;&middot;&nbsp; In stock',
                        'h1' => 'Signal-blocking protection, in stock today.',
                        'oneline' => 'Faraday enclosures that block cellular, WiFi, GPS, and radio signals to protect devices and data from interception. 100% First Nations-owned supplier.',
                    ],
                    [
                        'type' => 'faraday_feature',
                        'panel_label' => 'Ghost Mode &middot; Phone case',
                        'sec_h' => 'In stock &middot; $20 CAD',
                        'sec_t' => 'Faraday phone case',
                        'sec_sub' => 'Blocks cellular, WiFi, GPS, and radio signals to protect the phone and its data from interception while enclosed.',
                        'cta' => ['label' => 'Buy now &middot; $20 CAD', 'href' => 'https://buy.stripe.com/5kQ4gs2bQ5fbezScup6g800'],
                    ],
                    [
                        'type' => 'faraday_feature',
                        'panel_label' => 'Ghost Mode &middot; Utility case',
                        'sec_h' => 'In stock &middot; $15 CAD',
                        'sec_t' => 'Faraday utility case',
                        'sec_sub' => 'The larger enclosure: blocks cellular, WiFi, GPS, and radio signals to protect devices and data from interception.',
                        'cta' => ['label' => 'Buy now &middot; $15 CAD', 'href' => 'https://buy.stripe.com/aFa7sE03IcHDbnGeCx6g801'],
                    ],
                    [
                        'type' => 'faraday_feature',
                        'panel_label' => 'Ghost Mode &middot; Key fob 2-pack',
                        'sec_h' => 'In stock &middot; $10 CAD',
                        'sec_t' => 'Faraday key fob case, 2-pack',
                        'sec_sub' => 'A two-pack sized for key fobs: blocks cellular, WiFi, GPS, and radio signals to protect devices and data from interception.',
                        'cta' => ['label' => 'Buy now &middot; $10 CAD', 'href' => 'https://buy.stripe.com/5kQ00cdUybDz2Racup6g802'],
                    ],
                    [
                        'type' => 'cta_band_center',
                        'sec_h' => 'Bulk &amp; institutional',
                        'sec_t' => 'Equipping a team?',
                        'sec_sub' => 'Volume pricing for law enforcement, emergency management, and government buyers. An Indigenous-qualified supplier on the ISC and CCAB registries.',
                        'cta_primary' => ['label' => 'Book an assessment', 'href' => '/contact'],
                    ],
                    [
                        'type' => 'photo_strip',
                        'photos' => [
                            [
                                'src' => '/img/fnpi-booth-faraday.jpg',
                                'alt' => 'FNPI trade-show booth with Ghost Mode Faraday case banner.',
                                'caption' => 'The FNPI booth: the Ghost Mode Faraday line in the field.',
                            ],
                        ],
                    ],
                    [
                        'type' => 'text_center',
                        'sec_h' => 'Shipping &amp; returns',
                        'sec_t' => 'Ships from Canada.',
                        'sec_sub' => 'Questions or returns: <a href="/contact">contact us</a>.',
                    ],
                ],
            ],

            '/contact' => [
                'title' => 'Contact: Tell us what your Nation needs · FNPI',
                'meta_description' => 'Request a quote or get started. For First Nations exploring a sovereign platform, and for funders and partners who want to support the build.',
                'meta_robots' => null,
                'head_styles' => <<<'CSS'
                      .field select{width:100%;border:1px solid var(--line);border-radius:3px;padding:11px 13px;font:inherit;font-size:14px;background:#fff}
                      .funders{margin-top:18px;padding:16px 18px;background:#e3f4f8;border-radius:4px;color:#0f7488;font-size:14px}
                    CSS . "\n",
                'blocks' => [
                    [
                        'type' => 'hero',
                        'eyebrow' => 'Get started',
                        'h1' => 'Tell us what your Nation needs.',
                        'oneline' => 'Whether you are a Nation exploring a platform you own, or a partner or funder who wants to support the build, start here.',
                    ],
                    [
                        'type' => 'contact_form',
                    ],
                ],
            ],
        ];

        // Normalize head_styles line endings to LF. The CSS is authored here as
        // a PHP heredoc, so on a CRLF checkout (Windows autocrlf) it would carry
        // \r\n, while Twig normalizes its template sources to \n on load. Forcing
        // LF here keeps the seeded render byte-identical to the original template
        // regardless of how the source files are checked out.
        foreach ($pages as &$page) {
            if (\is_string($page['head_styles'])) {
                $page['head_styles'] = str_replace("\r\n", "\n", $page['head_styles']);
            }
        }
        unset($page);

        return $pages;
    }
}
