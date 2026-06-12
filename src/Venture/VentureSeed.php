<?php

declare(strict_types=1);

namespace App\Venture;

/**
 * Seed data for the Venture Numbers section: the six lanes, their gating
 * facts, and the provenance snapshot, mirrored from the external modeling
 * workbook on 2026-06-11.
 *
 * EVERY FIGURE HERE IS PLACEHOLDER-GRADE. The stated anchors (Yr 5 values,
 * sell-through scenarios, per-deal sizes, the likely roll-up shape) come from
 * the workbook brief; the intermediate-year values are smooth placeholder
 * ramps, not modeled values, entered so the grid and roll-up render. Each
 * lane's assumptions record the anchors verbatim so nothing is lost when a
 * placeholder cell gets corrected in the UI.
 *
 * Scenario rows are five whole-CAD yearly values, Yr 1 first.
 */
final class VentureSeed
{
    public const AS_OF = '2026-06-11';
    public const MODEL_VERSION = 'FNPI-revenue-model.xlsx';
    public const SNAPSHOT_NOTE = 'Initial mirror. Every figure is placeholder-grade until confirmed against the workbook.';

    /**
     * @return list<array{key:string, title:string, summary:string, grid:array<string,list<int>>, assumptions:list<string>, notes:string}>
     */
    public static function lanes(): array
    {
        return [
            [
                'key' => 'technology',
                'title' => 'Technology / Anokii',
                'summary' => 'The platform lane and the long-term engine: sovereign workspaces deployed Nation by Nation.',
                'grid' => [
                    'worst' => [90000, 280000, 560000, 900000, 1240000],
                    'likely' => [179000, 700000, 1400000, 2200000, 3060000],
                    'best' => [250000, 950000, 1800000, 2900000, 3860000],
                ],
                'assumptions' => [
                    'Modeled in FNPI-market-model.xlsx; Yr 5 ARR anchors: 1.24M worst, 2.48M likely, 3.86M best',
                    'Likely revenue path runs 179k to 3.06M over 5 years; the likely row follows that path',
                    'Assessment conversions land here and only here (about 1 in 3 assessments becomes an Anokii deployment); never double-counted',
                    'Intermediate-year values are placeholder interpolation, not modeled values',
                ],
                'notes' => '',
            ],
            [
                'key' => 'faraday',
                'title' => 'Faraday inventory',
                'summary' => 'Sell-through of the existing signal-blocking inventory; recovery, not a growth lane.',
                'grid' => [
                    'worst' => [4000, 4000, 3300, 0, 0],
                    'likely' => [38000, 38000, 37000, 0, 0],
                    'best' => [105000, 106000, 105000, 0, 0],
                ],
                'assumptions' => [
                    'About 50,000 units on hand across 3 SKUs',
                    'Real prices: utility $15, phone $20, key fobs 2 for $10',
                    '3-year sell-through scenarios: 2% worst, 20% likely, 56% best',
                    'Likely case recovers about $113k over 3 years; yearly split is a placeholder spread',
                    'No revenue modeled after Yr 3',
                ],
                'notes' => '',
            ],
            [
                'key' => 'sourcing',
                'title' => 'Sourcing & Services',
                'summary' => 'Running today; the history is real but unquantified, so the whole lane is placeholder mix.',
                'grid' => [
                    'worst' => [10000, 10000, 10000, 10000, 10000],
                    'likely' => [30000, 45000, 75000, 75000, 75000],
                    'best' => [100000, 200000, 400000, 400000, 400000],
                ],
                'assumptions' => [
                    'Placeholder deal mix per year: 2 x $5k worst, 5 x $15k likely, 10 x $40k best',
                    'Yr 1 and Yr 2 likely values ramp toward the steady mix; placeholders pending the deal history',
                ],
                'notes' => '',
            ],
            [
                'key' => 'assessments',
                'title' => 'Assessments',
                'summary' => 'Paid front door: scoped assessments that convert into Anokii deployments.',
                'grid' => [
                    'worst' => [15000, 15000, 15000, 15000, 15000],
                    'likely' => [25000, 50000, 75000, 75000, 75000],
                    'best' => [40000, 120000, 200000, 200000, 200000],
                ],
                'assumptions' => [
                    '$15k / $25k / $40k per assessment at 1 / 3 / 5 per year (worst / likely / best)',
                    'About 1 in 3 converts to an Anokii deployment, counted in the Technology lane only',
                    'Yr 1 and Yr 2 values ramp toward the steady rate; placeholders',
                ],
                'notes' => '',
            ],
            [
                'key' => 'defence',
                'title' => 'Defence',
                'summary' => 'Zero committed today; a Yr 3 onward ramp if the EOI path opens.',
                'grid' => [
                    'worst' => [0, 0, 0, 0, 0],
                    'likely' => [0, 0, 75000, 180000, 296000],
                    'best' => [0, 0, 130000, 320000, 520000],
                ],
                'assumptions' => [
                    '$0 committed; DAF EOI submitted 2026-06-11',
                    'Likely ramps from Yr 3 to about $296k/yr by Yr 5',
                    'Mix behind the ramp: materiel resale at 15% margin, INDGEN Edge units about $4,500 each, services',
                    'Worst stays $0; the best row is an unmodeled placeholder ramp',
                ],
                'notes' => '',
            ],
            [
                'key' => 'pathways',
                'title' => 'Pathways',
                'summary' => 'Roughly breakeven funnel work; the value is what it feeds, not the margin.',
                'grid' => [
                    'worst' => [0, 0, 0, 0, 0],
                    'likely' => [12500, 12500, 12500, 12500, 12500],
                    'best' => [25000, 25000, 25000, 25000, 25000],
                ],
                'assumptions' => [
                    '$2,500 x 5 per year, about 12 hours each; revenue shown, delivery cost roughly offsets it',
                    'Honest framing: a funnel into assessments and technology, not a margin lane',
                ],
                'notes' => '',
            ],
        ];
    }

    /**
     * @return list<array{key:string, lane_key:string, label:string, detail:string}>
     */
    public static function facts(): array
    {
        return [
            [
                'key' => 'faraday-landed-cost',
                'lane_key' => 'faraday',
                'label' => 'Landed cost',
                'detail' => 'Landed cost of the inventory is unknown; the recovery math moves with it.',
            ],
            [
                'key' => 'faraday-sku-split',
                'lane_key' => 'faraday',
                'label' => 'SKU split',
                'detail' => 'How the roughly 50,000 units split across the 3 SKUs is unconfirmed.',
            ],
            [
                'key' => 'faraday-test-data',
                'lane_key' => 'faraday',
                'label' => 'Independent test data',
                'detail' => 'No independent test data yet. No test data means no government sale.',
            ],
            [
                'key' => 'faraday-godaddy',
                'lane_key' => 'faraday',
                'label' => 'Current sell-through',
                'detail' => 'The current GoDaddy store sell-through rate is unquantified.',
            ],
            [
                'key' => 'sourcing-deal-history',
                'lane_key' => 'sourcing',
                'label' => 'Deal history',
                'detail' => 'The lane runs today but its history is unquantified; placeholder mix stands in until the deal history is assembled.',
            ],
            [
                'key' => 'defence-daf-eoi',
                'lane_key' => 'defence',
                'label' => 'DAF EOI outcome',
                'detail' => 'EOI submitted 2026-06-11; $0 committed until an outcome lands.',
            ],
        ];
    }
}
