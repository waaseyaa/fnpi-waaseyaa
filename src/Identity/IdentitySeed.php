<?php

declare(strict_types=1);

namespace App\Identity;

/**
 * Seed content for the Identity Workspace, ported 1:1 from
 * northops-fnpi-venture/fnpi-identity-workspace.html (the static artifact).
 *
 * SECTIONS holds per-section header metadata; PILLARS is the ordered list of
 * cards. Pills are [{t,cyan}]. Editable fields (status, notes) seed to their
 * artifact defaults / empty and are then owned by the database.
 */
final class IdentitySeed
{
    /** @return array<string, array{no:string, title:string, sub:string}> */
    public static function sections(): array
    {
        return [
            'foundation' => [
                'no' => '01',
                'title' => 'Foundation',
                'sub' => 'The bedrock. Everything else should trace back to these four. This is where the stale vision and mission get refreshed and the missing pieces get written.',
            ],
            'positioning' => [
                'no' => '02',
                'title' => 'Positioning & architecture',
                'sub' => 'Who FNPI is in the market, how it is defensibly different, and how the names fit together. This is the most developed area already.',
            ],
            'audiences' => [
                'no' => '03',
                'title' => 'Audiences & messaging',
                'sub' => 'Who we speak to, and the one thing each needs to hear. Defense and security buyers stay private, off all public materials.',
            ],
            'verbal' => [
                'no' => '04',
                'title' => 'Verbal identity',
                'sub' => 'How FNPI sounds, and the tagline question.',
            ],
            'visual' => [
                'no' => '05',
                'title' => 'Visual identity',
                'sub' => 'What FNPI looks like. Mostly settled in practice, not yet documented as a system.',
            ],
            'cultural' => [
                'no' => '06',
                'title' => 'Cultural grounding',
                'sub' => 'For an Indigenous-owned company this is not decoration, it is the foundation. The strongest Indigenous brands put heritage and values front and centre as lived practice.',
            ],
            'proof' => [
                'no' => '07',
                'title' => 'Proof & credentials',
                'sub' => 'The evidence the identity stands on. This is strong and documented.',
            ],
        ];
    }

    /**
     * @return list<array{
     *   pid:string, section:string, title:string, now_label:string, body:string,
     *   is_quote:int, decide_label:string, decision:string, status:string,
     *   pills:list<array{t:string,cyan:bool}>, is_full:int, sort:int
     * }>
     */
    public static function pillars(): array
    {
        return [
            // 01 Foundation
            [
                'pid' => 'purpose', 'section' => 'foundation', 'title' => 'Purpose (the why)',
                'now_label' => 'Now',
                'body' => 'Not stated. Implied across the work: turn Indigenous procurement qualification into real, owned capability that keeps money, data, and control in the community.',
                'is_quote' => 0, 'decide_label' => 'Decide',
                'decision' => 'write one sentence on why FNPI exists beyond making money.',
                'status' => 'gap', 'pills' => [], 'is_full' => 0, 'sort' => 1,
            ],
            [
                'pid' => 'values', 'section' => 'foundation', 'title' => 'Values / principles',
                'now_label' => 'Now',
                'body' => 'Not formalized. De facto: Indigenous ownership, sovereignty, OCAP, non-extractive ("in a good way"), capability over pass-through, honesty (label the stage).',
                'is_quote' => 0, 'decide_label' => 'Decide',
                'decision' => 'lock 4 to 6 named values, each with a one-line meaning.',
                'status' => 'gap',
                'pills' => [
                    ['t' => 'Sovereignty', 'cyan' => false], ['t' => 'OCAP', 'cyan' => false],
                    ['t' => 'Non-extractive', 'cyan' => false], ['t' => 'Real capability', 'cyan' => false],
                    ['t' => 'Honesty', 'cyan' => false], ['t' => 'Community first', 'cyan' => false],
                ],
                'is_full' => 0, 'sort' => 2,
            ],
            [
                'pid' => 'vision', 'section' => 'foundation', 'title' => 'Vision (the future we are building)',
                'now_label' => 'Current, legacy (on the old site)',
                'body' => 'To be a trusted leader in Indigenous engagement and strengthen national sovereignty while fostering economic sovereignty.',
                'is_quote' => 1, 'decide_label' => 'Decide',
                'decision' => 'keep, refresh, or replace. It is decent but generic and predates the platform thesis. A sharper vision names the world FNPI is trying to create (Nations that own their tools, data, and economy).',
                'status' => 'draft', 'pills' => [], 'is_full' => 1, 'sort' => 3,
            ],
            [
                'pid' => 'mission', 'section' => 'foundation', 'title' => 'Mission (what we do, day to day)',
                'now_label' => 'Current, legacy (on the old site)',
                'body' => 'To provide exceptional sourcing and services solutions that strengthen economic self-determination, building trusted industry relationships and a legacy of strength and opportunity for generations to come.',
                'is_quote' => 1, 'decide_label' => 'Rewrite',
                'decision' => 'this is sourcing-only. It says nothing about the sovereign platform (Anokii), protection (Faraday), or the procurement-enablement offer in the Matthew package. The mission has to cover what FNPI actually does now.',
                'status' => 'work', 'pills' => [], 'is_full' => 1, 'sort' => 4,
            ],
            // 02 Positioning & architecture
            [
                'pid' => 'spine', 'section' => 'positioning', 'title' => 'Positioning spine',
                'now_label' => 'From fnpi-narrative.md',
                'body' => 'FNPI turns Indigenous procurement qualification into a platform: one 100% First Nations-owned company delivering sourcing, sovereign technology, and protection, with the same qualification opening every door. The qualification is the moat. Sovereignty is the purpose.',
                'is_quote' => 1, 'decide_label' => 'Tension to resolve',
                'decision' => 'the Matthew package adds a fourth thread, procurement enablement (helping FN businesses get registered and bid). Does that become a named lane, or a service under sourcing? It needs a home in the story.',
                'status' => 'defined', 'pills' => [], 'is_full' => 1, 'sort' => 5,
            ],
            [
                'pid' => 'architecture', 'section' => 'positioning', 'title' => 'Brand architecture (the names)',
                'now_label' => 'Now',
                'body' => 'FNPI = the company. Anokii = product for Nations. Co-Intelligence = the AI in Anokii. INGEN = product for companies. Waaseyaa = the engine underneath.',
                'is_quote' => 0, 'decide_label' => 'Decide',
                'decision' => 'name and place the procurement-enablement service; confirm which names are public vs internal.',
                'status' => 'draft',
                'pills' => [
                    ['t' => 'FNPI', 'cyan' => true], ['t' => 'Anokii', 'cyan' => true],
                    ['t' => 'Co-Intelligence', 'cyan' => true], ['t' => 'INGEN', 'cyan' => true],
                    ['t' => 'Waaseyaa', 'cyan' => true], ['t' => '+ procurement service?', 'cyan' => false],
                ],
                'is_full' => 0, 'sort' => 6,
            ],
            [
                'pid' => 'moat', 'section' => 'positioning', 'title' => 'Differentiation / moat',
                'now_label' => 'Now',
                'body' => 'Four layers, only FNPI has all four: it is the procurement vehicle, sovereignty is real, it owns the technology, and it is already built.',
                'is_quote' => 0, 'decide_label' => 'Keep',
                'decision' => 'this is strong and well documented. Carry it through every channel.',
                'status' => 'defined', 'pills' => [], 'is_full' => 0, 'sort' => 7,
            ],
            // 03 Audiences & messaging
            [
                'pid' => 'aud-nations', 'section' => 'audiences', 'title' => 'First Nations (Councils & admins)',
                'now_label' => 'Message',
                'body' => 'Buy sovereign technology and services you actually own. The primary buyer for Anokii.',
                'is_quote' => 0, 'decide_label' => '', 'decision' => '',
                'status' => 'draft', 'pills' => [], 'is_full' => 0, 'sort' => 8,
            ],
            [
                'pid' => 'aud-buyers', 'section' => 'audiences', 'title' => 'Government & corporate buyers',
                'now_label' => 'Message',
                'body' => 'Meet your Indigenous procurement commitments with real capability, not a pass-through.',
                'is_quote' => 0, 'decide_label' => '', 'decision' => '',
                'status' => 'draft', 'pills' => [], 'is_full' => 0, 'sort' => 9,
            ],
            [
                'pid' => 'aud-funders', 'section' => 'audiences', 'title' => 'Funders & partners',
                'now_label' => 'Message',
                'body' => 'Support Nation-owned digital sovereignty. AEP, FedNor, IFIs, Web Networks, and allies.',
                'is_quote' => 0, 'decide_label' => '', 'decision' => '',
                'status' => 'draft', 'pills' => [], 'is_full' => 0, 'sort' => 10,
            ],
            [
                'pid' => 'aud-fnbiz', 'section' => 'audiences', 'title' => 'FN businesses (new, from the package)',
                'now_label' => 'Message',
                'body' => 'Tell us what you sell. We will help you get registered, compliant, and ready to bid. The NACCA / FNPA vendor-onboarding offer.',
                'is_quote' => 0, 'decide_label' => 'Decide',
                'decision' => "is this audience part of FNPI's public identity, or a separate offer? It changes the brand's center of gravity.",
                'status' => 'work', 'pills' => [], 'is_full' => 0, 'sort' => 11,
            ],
            // 04 Verbal identity
            [
                'pid' => 'voice', 'section' => 'verbal', 'title' => 'Voice & tone',
                'now_label' => 'Emerging (house style)',
                'body' => 'Plain, honest, grounded. No em dashes. Label the stage (running today / building / growing). Confident, never fear-selling. Respectful to Nations, never patronizing or about price.',
                'is_quote' => 0, 'decide_label' => 'Decide',
                'decision' => 'formalize this into a short voice guide so it survives across writers and into Anokii.',
                'status' => 'draft', 'pills' => [], 'is_full' => 0, 'sort' => 12,
            ],
            [
                'pid' => 'tagline', 'section' => 'verbal', 'title' => 'Tagline',
                'now_label' => 'Options', 'body' => '', 'is_quote' => 0, 'decide_label' => 'Decide',
                'decision' => 'pick the primary. Internal lean from the narrative, procurement qualifies us, sovereignty sells us.',
                'status' => 'gap',
                'pills' => [
                    ['t' => 'Service & Sourcing Solutions (legacy)', 'cyan' => false],
                    ['t' => 'Sourcing, technology, security. Built on sovereignty.', 'cyan' => false],
                    ['t' => 'Indigenous procurement, expanded.', 'cyan' => false],
                    ['t' => 'One qualification. Every solution.', 'cyan' => false],
                    ['t' => 'Sovereignty, delivered.', 'cyan' => false],
                ],
                'is_full' => 0, 'sort' => 13,
            ],
            // 05 Visual identity
            [
                'pid' => 'logo', 'section' => 'visual', 'title' => 'Logo',
                'now_label' => 'Now',
                'body' => 'The wolf mark plus FNPI wordmark. Now self-hosted on the live site.',
                'is_quote' => 0, 'decide_label' => 'Decide',
                'decision' => 'is the existing wolf mark the long-term mark, or does the expanded company warrant a refresh?',
                'status' => 'defined', 'pills' => [], 'is_full' => 0, 'sort' => 14,
            ],
            [
                'pid' => 'paletteType', 'section' => 'visual', 'title' => 'Palette & type',
                'now_label' => 'Now (de facto)',
                'body' => 'Black and white with tech-cyan (#1fb6d6 / #13869f). Display type Anton, condensed Oswald.',
                'is_quote' => 0, 'decide_label' => 'Decide',
                'decision' => 'document it as a real system (tokens, usage) so every surface and Anokii instance is consistent.',
                'status' => 'draft', 'pills' => [], 'is_full' => 0, 'sort' => 15,
            ],
            [
                'pid' => 'imagery', 'section' => 'visual', 'title' => 'Imagery & iconography',
                'now_label' => 'Now',
                'body' => 'No system. The site uses inline SVG icons and grid motifs ad hoc.',
                'is_quote' => 0, 'decide_label' => 'Decide',
                'decision' => 'an image and icon direction that feels Indigenous, sovereign, and modern without being clip-art. A good place to work with an Indigenous design lens.',
                'status' => 'gap', 'pills' => [], 'is_full' => 1, 'sort' => 16,
            ],
            // 06 Cultural grounding
            [
                'pid' => 'culture', 'section' => 'cultural', 'title' => 'Anishinaabe foundation',
                'now_label' => 'Now',
                'body' => 'Implicit, not articulated. FNPI is rooted at Sagamok Anishnawbek. The product names are Anishinaabemowin (Waaseyaa, Anokii). Sovereignty and OCAP run through everything as lived values, not slogans.',
                'is_quote' => 0, 'decide_label' => 'Decide, with care and community input',
                'decision' => 'how explicit and how loud the cultural grounding should be, what the names mean and why they were chosen, and how to honour the language and teachings authentically rather than as branding veneer.',
                'status' => 'gap', 'pills' => [], 'is_full' => 1, 'sort' => 17,
            ],
            // 07 Proof & credentials
            [
                'pid' => 'proof', 'section' => 'proof', 'title' => 'What backs the story',
                'now_label' => '', 'body' => '', 'is_quote' => 0, 'decide_label' => 'Keep current',
                'decision' => 'reconcile as registrations and the reference build firm up. The Matthew package documents the procurement credentials.',
                'status' => 'defined',
                'pills' => [
                    ['t' => '100% First Nations-owned', 'cyan' => true],
                    ['t' => 'Since 2017', 'cyan' => true],
                    ['t' => 'Sagamok Anishnawbek', 'cyan' => true],
                    ['t' => 'ISC Business Directory', 'cyan' => false],
                    ['t' => 'CCAB / CCIB certified', 'cyan' => false],
                    ['t' => 'FNPA (early registrant)', 'cyan' => false],
                    ['t' => 'Reference build (live)', 'cyan' => false],
                    ['t' => 'Faraday inventory shipping', 'cyan' => false],
                ],
                'is_full' => 1, 'sort' => 18,
            ],
        ];
    }
}
