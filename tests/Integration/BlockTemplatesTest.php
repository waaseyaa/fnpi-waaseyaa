<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Copy-refresh enablement contracts for the block templates:
 *
 * - feature_lanes renders three lanes today and accepts an optional lane4 of
 *   the same shape (tag/h3/body, optional href/go). A lane with an href is a
 *   link card; four lanes switch the grid to the lanes-4 layout.
 * - vision_mission iterates blk.items, so a third item (e.g. Values) is a
 *   content-only change.
 * - Anishinaabemowin stays draft-side: *_oj fields that agent drafts carry
 *   (e.g. hero h1_oj) must never reach public output until oj rendering is
 *   deliberately designed.
 */
final class BlockTemplatesTest extends TestCase
{
    private static Environment $twig;

    public static function setUpBeforeClass(): void
    {
        $provider = new SsrServiceProvider();
        $provider->setKernelContext(dirname(__DIR__, 2), [], []);
        $provider->boot();
        $twig = SsrServiceProvider::getTwigEnvironment();
        self::assertNotNull($twig, 'Twig environment must be booted.');
        self::$twig = $twig;
    }

    /**
     * @return array<string, mixed>
     */
    private static function lanesBlock(): array
    {
        return [
            'type' => 'feature_lanes',
            'sec_h' => 'What we do',
            'sec_t' => 'Three lanes, one qualification',
            'sec_sub' => 'One foundation.',
            'lane1' => ['tag' => 'Running today', 'h3' => 'Sourcing', 'body' => 'Sourcing body.'],
            'lane2' => ['href' => '/technology', 'tag' => 'Building', 'h3' => 'Technology', 'body' => 'Technology body.', 'go' => 'Explore'],
            'lane3' => ['tag' => 'Shipping now', 'h3' => 'Protection', 'body' => 'Protection body.'],
        ];
    }

    #[Test]
    public function feature_lanes_renders_three_lanes_on_the_three_column_grid(): void
    {
        $html = self::$twig->render('blocks/feature_lanes.html.twig', ['blk' => self::lanesBlock()]);

        $this->assertSame(3, substr_count($html, '<span class="tag">'), 'Exactly the three seeded lanes render.');
        $this->assertStringContainsString('class="lanes"', $html);
        $this->assertStringNotContainsString('lanes-4', $html, 'Three lanes must not opt into the four-lane grid.');
        // The seeded link lane keeps its link-card treatment, the others stay plain.
        $this->assertStringContainsString('<a class="lane lane--link" href="/technology">', $html);
        $this->assertStringContainsString('<span class="lane-go">Explore</span>', $html);
        $this->assertSame(2, substr_count($html, '<div class="lane">'));
    }

    #[Test]
    public function feature_lanes_renders_an_optional_fourth_lane(): void
    {
        $blk = self::lanesBlock();
        $blk['lane4'] = ['tag' => 'Fourth lane', 'h3' => 'Pathways', 'body' => 'Pathways body.'];

        $html = self::$twig->render('blocks/feature_lanes.html.twig', ['blk' => $blk]);

        $this->assertSame(4, substr_count($html, '<span class="tag">'));
        $this->assertStringContainsString('class="lanes lanes-4"', $html, 'Four lanes switch to the lanes-4 grid.');
        $this->assertStringContainsString('Pathways body.', $html);
        $this->assertSame(3, substr_count($html, '<div class="lane">'), 'A lane4 without href renders as a plain card.');
        $this->assertSame(4, substr_count($html, '<svg class="ic"'), 'Every lane keeps an icon.');
    }

    #[Test]
    public function a_fourth_lane_with_href_and_go_renders_as_a_link_card(): void
    {
        $blk = self::lanesBlock();
        $blk['lane4'] = ['href' => '/how-it-works', 'tag' => 'Fourth lane', 'h3' => 'Pathways', 'body' => 'Pathways body.', 'go' => 'See the playbook'];

        $html = self::$twig->render('blocks/feature_lanes.html.twig', ['blk' => $blk]);

        $this->assertStringContainsString('<a class="lane lane--link" href="/how-it-works">', $html);
        $this->assertStringContainsString('<span class="lane-go">See the playbook</span>', $html);
        $this->assertSame(2, substr_count($html, '</a>'), 'lane2 and lane4 are both link cards.');
    }

    #[Test]
    public function vision_mission_renders_every_item_so_a_third_needs_no_code(): void
    {
        $html = self::$twig->render('blocks/vision_mission.html.twig', ['blk' => [
            'type' => 'vision_mission',
            'sec_h' => 'About',
            'sec_t' => 'A trusted partner',
            'items' => [
                ['lbl' => 'Our Vision', 'body' => 'Vision body.'],
                ['lbl' => 'Our Mission', 'body' => 'Mission body.'],
                ['lbl' => 'Our Values', 'body' => 'Values body.'],
            ],
        ]]);

        $this->assertSame(3, substr_count($html, '<div class="vm">'));
        foreach (['Our Vision', 'Our Mission', 'Our Values'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
    }

    #[Test]
    public function vision_mission_renders_a_list_body_as_discrete_paragraphs(): void
    {
        // The four values render as four discrete items when the body is a
        // list; a string body stays the single paragraph it always was.
        $html = self::$twig->render('blocks/vision_mission.html.twig', ['blk' => [
            'type' => 'vision_mission',
            'sec_h' => 'About',
            'sec_t' => 'A trusted partner',
            'items' => [
                ['lbl' => 'Our Purpose', 'body' => 'One paragraph.'],
                ['lbl' => 'Our Values', 'body' => [
                    'Inclusion: first value.',
                    'Transparency: second value.',
                    'Accountability: third value.',
                    'Collaboration: fourth value.',
                ]],
            ],
        ]]);

        $this->assertSame(2, substr_count($html, '<div class="vm">'));
        $this->assertStringContainsString('<p>One paragraph.</p>', $html);
        foreach (['Inclusion: first value.', 'Transparency: second value.', 'Accountability: third value.', 'Collaboration: fourth value.'] as $value) {
            $this->assertStringContainsString('<p>' . $value . '</p>', $html);
        }
    }

    #[Test]
    public function no_public_template_references_draft_side_anishinaabemowin_fields(): void
    {
        // The public render surface: the root templates (base/page/404 and the
        // retired hand-coded pages) and every block partial. The authed Anokii
        // UI under templates/anokii and templates/admin may legitimately SHOW
        // *_oj draft fields to editors; the public surface must not emit them
        // until oj rendering is deliberately designed.
        $root = dirname(__DIR__, 2) . '/templates';
        $publicTemplates = array_merge(
            glob($root . '/*.twig') ?: [],
            glob($root . '/*.twig.disabled') ?: [],
            glob($root . '/blocks/*.twig') ?: [],
        );
        $this->assertNotEmpty($publicTemplates);

        $hits = [];
        foreach ($publicTemplates as $template) {
            if (str_contains((string) file_get_contents($template), '_oj')) {
                $hits[] = basename($template);
            }
        }

        $this->assertSame([], $hits, 'Public templates must not reference *_oj fields; Anishinaabemowin stays draft-side until oj rendering is deliberately designed.');
    }

    #[Test]
    public function hero_never_renders_draft_side_anishinaabemowin_fields(): void
    {
        $html = self::$twig->render('blocks/hero.html.twig', ['blk' => [
            'type' => 'hero',
            'eyebrow' => 'Eyebrow',
            'h1' => 'Four layers. Only FNPI has all four.',
            'h1_oj' => 'Niiwin apatchitchiganan ayaawan: FNPI eta kakina niiwin odayaanan.',
            'oneline' => 'Oneline.',
            'oneline_oj' => 'Oneline oj.',
        ]]);

        $this->assertStringContainsString('Four layers. Only FNPI has all four.', $html);
        $this->assertStringNotContainsString('Niiwin', $html, 'h1_oj must not render publicly until oj output is deliberately designed.');
        $this->assertStringNotContainsString('Oneline oj.', $html);
    }

    #[Test]
    public function vision_mission_renders_structured_values_as_a_full_width_band(): void
    {
        // A body of {lbl, text} entries leaves the column grid and renders as
        // the full-width values band: four discrete labelled items, no
        // orphaned grid cell. Rendered text stays 'Label: clause'.
        $html = self::$twig->render('blocks/vision_mission.html.twig', ['blk' => [
            'type' => 'vision_mission',
            'sec_h' => 'About',
            'sec_t' => 'A trusted partner',
            'items' => [
                ['lbl' => 'Our Purpose', 'body' => 'P.'],
                ['lbl' => 'Our Vision', 'body' => 'V.'],
                ['lbl' => 'Our Mission', 'body' => 'M.'],
                ['lbl' => 'Our Values', 'body' => [
                    ['lbl' => 'Inclusion', 'text' => 'first clause.'],
                    ['lbl' => 'Transparency', 'text' => 'second clause.'],
                    ['lbl' => 'Accountability', 'text' => 'third clause.'],
                    ['lbl' => 'Collaboration', 'text' => 'fourth clause.'],
                ]],
            ],
        ]]);

        $this->assertSame(3, substr_count($html, '<div class="vm">'), 'only the plain items sit in the column grid');
        $this->assertSame(1, substr_count($html, 'vm--values'));
        $this->assertSame(4, substr_count($html, 'class="vm-value"'));
        $this->assertStringContainsString('<b>Inclusion:</b> first clause.', $html);
        $this->assertStringContainsString('<b>Collaboration:</b> fourth clause.', $html);
        $this->assertStringContainsString('Our Values', $html);
    }

    #[Test]
    public function vision_mission_without_structured_values_renders_no_band(): void
    {
        // The general-case guard: 2-3 plain items (string or list-of-string
        // bodies) keep today's markup exactly: every item in the grid, no band.
        $html = self::$twig->render('blocks/vision_mission.html.twig', ['blk' => [
            'type' => 'vision_mission',
            'sec_h' => 'About',
            'sec_t' => 'A trusted partner',
            'items' => [
                ['lbl' => 'Our Vision', 'body' => 'V.'],
                ['lbl' => 'Our Mission', 'body' => ['M one.', 'M two.']],
            ],
        ]]);

        $this->assertSame(2, substr_count($html, '<div class="vm">'));
        $this->assertStringNotContainsString('vm--values', $html);
        $this->assertStringNotContainsString('vm-value', $html);
        $this->assertStringContainsString('<p>M one.</p>', $html);
        $this->assertStringContainsString('<p>M two.</p>', $html);
    }

    #[Test]
    public function photo_strip_renders_captioned_figures_with_required_alt_text(): void
    {
        $html = self::$twig->render('blocks/photo_strip.html.twig', ['blk' => [
            'type' => 'photo_strip',
            'sec_h' => 'Track record',
            'h2' => 'Ten years in the room.',
            'photos' => [
                ['src' => '/img/a.jpg', 'alt' => 'Alt one.', 'caption' => 'Caption one.'],
                ['src' => '/img/b.jpg', 'alt' => 'Alt two.', 'caption' => 'Caption two.'],
            ],
        ]]);

        $this->assertSame(2, substr_count($html, '<figure>'));
        $this->assertStringContainsString('class="strip strip-2"', $html);
        $this->assertStringContainsString('src="/img/a.jpg" alt="Alt one."', $html);
        $this->assertStringContainsString('src="/img/b.jpg" alt="Alt two."', $html);
        $this->assertStringContainsString('<figcaption>Caption one.</figcaption>', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    #[Test]
    public function the_home_seed_photo_strip_references_shipped_image_files(): void
    {
        // Every image the seed places must exist as an optimized derivative in
        // public/img (approved images only; originals stay in the Drive).
        $home = \App\Pages\PageSeedData::all()['/'];
        $strip = null;
        foreach ($home['blocks'] as $block) {
            if ($block['type'] === 'photo_strip') {
                $strip = $block;
            }
        }
        $this->assertNotNull($strip, 'home seed carries the photo strip');
        $this->assertNotEmpty($strip['photos']);
        foreach ($strip['photos'] as $photo) {
            $this->assertNotSame('', trim($photo['alt'] ?? ''), 'alt text is required per photo');
            $this->assertFileExists(dirname(__DIR__, 2) . '/public' . $photo['src']);
        }
    }

    #[Test]
    public function cta_band_center_renders_structured_doors_as_equal_panels(): void
    {
        // The two-door closing band: each door is a labelled panel with its own
        // button; the first door is the cyan primary, the second the ink button.
        $html = self::$twig->render('blocks/cta_band_center.html.twig', ['blk' => [
            'type' => 'cta_band_center',
            'sec_h' => 'Get in touch',
            'sec_t' => 'Tell us what you need.',
            'doors' => [
                ['label' => 'For Nations', 'text' => 'Door one text.', 'cta' => ['label' => 'Book an assessment', 'href' => '/contact']],
                ['label' => 'For governments & industry', 'text' => 'Door two text.', 'cta' => ['label' => 'Defence & Security', 'href' => '/defence']],
            ],
        ]]);

        $this->assertSame(2, substr_count($html, '<div class="door">'));
        $this->assertStringContainsString('<a class="btn cyan" href="/contact">Book an assessment</a>', $html);
        $this->assertStringContainsString('<a class="btn" href="/defence">Defence & Security</a>', $html);
        $this->assertStringContainsString('For Nations', $html);
        $this->assertStringContainsString('For governments & industry', $html);
        $this->assertStringNotContainsString('sec-sub', $html, 'doors replace the sec_sub paragraph');
    }

    #[Test]
    public function cta_band_center_without_doors_renders_the_original_contract(): void
    {
        // The general-case guard: sec_sub + the two-button row, exactly as today.
        $html = self::$twig->render('blocks/cta_band_center.html.twig', ['blk' => [
            'type' => 'cta_band_center',
            'sec_h' => 'H',
            'sec_t' => 'T',
            'sec_sub' => 'Sub text.',
            'cta_primary' => ['label' => 'P', 'href' => '/contact'],
            'cta_secondary' => ['label' => 'S', 'href' => '/defence'],
        ]]);

        $this->assertStringContainsString('<p class="sec-sub">Sub text.</p>', $html);
        $this->assertStringContainsString('<a class="btn cyan" href="/contact">P</a>', $html);
        $this->assertStringContainsString('<a class="btn" href="/defence">S</a>', $html);
        $this->assertStringNotContainsString('class="door"', $html);
    }

    #[Test]
    public function module_grid_omits_an_absent_subline_instead_of_an_empty_paragraph(): void
    {
        // The defence capability grid drops its subline (the intro already
        // carries the sentence); no empty sec-sub paragraph may remain.
        $html = self::$twig->render('blocks/module_grid.html.twig', ['blk' => [
            'type' => 'module_grid',
            'sec_h' => 'Capability',
            'h2' => 'Four areas.',
            'mods' => [['h4' => 'A', 'body' => 'B']],
        ]]);
        $this->assertStringNotContainsString('sec-sub', $html);
    }

    #[Test]
    public function hero_and_hero_cta_render_a_single_button_when_secondary_is_absent(): void
    {
        // A CTA without a secondary renders one button and no empty ghost
        // (the defence page drops its platform CTA).
        $hero = self::$twig->render('blocks/hero.html.twig', ['blk' => [
            'type' => 'hero',
            'eyebrow' => 'E',
            'h1' => 'H',
            'oneline' => 'O',
            'cta' => ['primary' => ['label' => 'Contact us', 'href' => '/contact']],
        ]]);
        $this->assertStringContainsString('Contact us', $hero);
        $this->assertStringNotContainsString('btn ghost', $hero);
        $this->assertStringNotContainsString('href=""', $hero);

        $band = self::$twig->render('blocks/hero_cta.html.twig', ['blk' => [
            'type' => 'hero_cta',
            'h2' => 'H2',
            'cta_primary' => ['label' => 'Contact us', 'href' => '/contact'],
        ]]);
        $this->assertStringContainsString('Contact us', $band);
        $this->assertStringNotContainsString('btn ghost', $band);
        $this->assertStringNotContainsString('href=""', $band);
    }
}
