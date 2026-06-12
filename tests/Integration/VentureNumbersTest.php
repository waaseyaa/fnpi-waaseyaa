<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Access\WorkspaceAccess;
use App\Anokii\Modules;
use App\Controller\VenturesController;
use App\Entity\GatingFact;
use App\Entity\VentureLane;
use App\Provider\AnokiiServiceProvider;
use App\Venture\VentureSeed;
use App\Venture\VentureService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\RevisionableEntityInterface;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Venture Numbers: the staff-only revenue-model mirror. Covers the entity
 * capabilities, the first permission-gated READ in the workspace (view must be
 * FORBIDDEN without `view ventures`, not merely neutral, so the query layer
 * drops rows too), the roll-up math against the seeded placeholder data, the
 * routes, and the chat-first template (placeholder banner, lane cards, fact
 * pills). Storage round-trips ride the same revision substrate the other four
 * entity tools already prove.
 */
final class VentureNumbersTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $provider = new SsrServiceProvider();
        $provider->setKernelContext(dirname(__DIR__, 2), [], []);
        $provider->boot();
    }

    /** A controllable account for policy assertions (WorkspaceAccessTest pattern). */
    private function account(bool $authenticated, array $permissions, array $roles = []): AccountInterface
    {
        return new class($authenticated, $permissions, $roles) implements AccountInterface {
            /** @param list<string> $permissions @param list<string> $roles */
            public function __construct(
                private readonly bool $authenticated,
                private readonly array $permissions,
                private readonly array $roles,
            ) {}

            public function id(): int|string
            {
                return 1;
            }

            public function hasPermission(string $permission): bool
            {
                return in_array('administrator', $this->roles, true)
                    || in_array($permission, $this->permissions, true);
            }

            public function getRoles(): array
            {
                return $this->roles;
            }

            public function isAuthenticated(): bool
            {
                return $this->authenticated;
            }
        };
    }

    private function seededLane(array $data, int $sortOrder = 10): VentureLane
    {
        $lane = new VentureLane();
        $lane->fill(
            $data['key'],
            $data['title'],
            $data['summary'],
            $data['grid'],
            $data['assumptions'],
            $data['notes'],
            $sortOrder,
            0,
            'Seed (model mirror)',
            '2026-06-11T00:00:00Z',
        );

        return $lane;
    }

    #[Test]
    public function venture_entities_are_revisionable_and_round_trip(): void
    {
        $lane = $this->seededLane(VentureSeed::lanes()[0]);
        $this->assertInstanceOf(RevisionableEntityInterface::class, $lane);
        $this->assertSame('technology', $lane->getKey());
        $this->assertSame(3060000, $lane->getScenarioValue(5, 'likely'));
        $this->assertSame(179000, $lane->getScenarioGrid()['likely'][0]);
        $this->assertNotSame([], $lane->getAssumptions());

        $fact = new GatingFact();
        $fact->fill('faraday-test-data', 'faraday', 'Independent test data', 'No test data, no government sale.', 'placeholder', 10, 0, 'Seed', '2026-06-11T00:00:00Z');
        $this->assertInstanceOf(RevisionableEntityInterface::class, $fact);
        $this->assertFalse($fact->isConfirmed());

        $fact->setStatus('confirmed')->setConfirmedBy(2, 'Matthew', '2026-06-12T00:00:00Z');
        $this->assertTrue($fact->isConfirmed());
        $this->assertSame('Matthew', $fact->getConfirmedByLabel());

        $fact->setStatus('placeholder')->clearConfirmation();
        $this->assertSame('', $fact->getConfirmedByLabel());
        $this->assertSame(0, $fact->getConfirmedByUid());
    }

    #[Test]
    public function scenario_field_helpers_cover_the_full_grid(): void
    {
        $fields = VentureLane::scenarioFields();
        $this->assertCount(15, $fields);
        $this->assertContains('y1_worst', $fields);
        $this->assertContains('y5_best', $fields);
        $this->assertSame('y3_likely', VentureLane::scenarioField(3, 'likely'));
    }

    #[Test]
    public function view_is_forbidden_without_the_permission_not_neutral(): void
    {
        $handler = WorkspaceAccess::handler();

        // An authenticated account WITHOUT `view ventures` (the Viewer role
        // shape, and any future non-staff account): Forbidden, the one result
        // the entity query layer drops rows on.
        $viewer = $this->account(true, []);
        foreach ([new VentureLane(), new GatingFact()] as $entity) {
            $result = $handler->check($entity, 'view', $viewer);
            $this->assertFalse($result->isAllowed());
            $this->assertTrue($result->isForbidden(), 'venture view denial must be Forbidden, not Neutral');
        }

        // Anonymous: same hard denial.
        $anon = $this->account(false, []);
        $this->assertTrue($handler->check(new VentureLane(), 'view', $anon)->isForbidden());

        // Staff with the permission, and the administrator role, may read.
        $staff = $this->account(true, [WorkspaceAccess::VIEW_VENTURES]);
        $this->assertTrue($handler->check(new VentureLane(), 'view', $staff)->isAllowed());
        $admin = $this->account(true, [], ['administrator']);
        $this->assertTrue($handler->check(new GatingFact(), 'view', $admin)->isAllowed());
    }

    #[Test]
    public function writes_and_confirms_have_their_own_gates(): void
    {
        $handler = WorkspaceAccess::handler();

        $editorShape = $this->account(true, [WorkspaceAccess::VIEW_VENTURES, WorkspaceAccess::EDIT_VENTURES, WorkspaceAccess::CONFIRM_VENTURES]);
        $this->assertTrue($handler->check(new VentureLane(), 'update', $editorShape)->isAllowed());
        $this->assertTrue($handler->check(new GatingFact(), 'confirm', $editorShape)->isAllowed());
        $this->assertTrue($handler->checkCreateAccess('venture_lane', '', $editorShape)->isAllowed());
        // No delete for editors: the section is no-delete by posture.
        $this->assertFalse($handler->check(new VentureLane(), 'delete', $editorShape)->isAllowed());

        $viewOnlyStaff = $this->account(true, [WorkspaceAccess::VIEW_VENTURES]);
        $this->assertFalse($handler->check(new VentureLane(), 'update', $viewOnlyStaff)->isAllowed());
        $this->assertFalse($handler->check(new GatingFact(), 'confirm', $viewOnlyStaff)->isAllowed());

        // The Editor role definition carries the venture permissions (the
        // Matt-can-edit bar); the Viewer role carries none of them.
        $roles = WorkspaceAccess::roles();
        foreach ([WorkspaceAccess::VIEW_VENTURES, WorkspaceAccess::EDIT_VENTURES, WorkspaceAccess::CONFIRM_VENTURES] as $perm) {
            $this->assertContains($perm, $roles['editor']['permissions']);
            $this->assertNotContains($perm, $roles['viewer']['permissions']);
        }
        $this->assertContains(WorkspaceAccess::ADMINISTER_VENTURES, $roles['administrator']['permissions']);
        $this->assertNotContains(WorkspaceAccess::ADMINISTER_VENTURES, $roles['editor']['permissions']);
    }

    #[Test]
    public function rollup_matches_the_model_anchors(): void
    {
        $lanes = [];
        foreach (VentureSeed::lanes() as $i => $data) {
            $lanes[] = $this->seededLane($data, ($i + 1) * 10);
        }
        $totals = VentureService::rollup($lanes);

        // The likely roll-up anchors from the workbook brief: roughly $270k in
        // Yr 1 growing to roughly $3.5M in Yr 5, Technology about 87% of Yr 5.
        $this->assertSame(284500, $totals['likely'][0]);
        $this->assertSame(3518500, $totals['likely'][4]);

        $tech = $lanes[0];
        $this->assertSame('technology', $tech->getKey());
        $this->assertSame(87, VentureService::shareOfYear($totals, $tech, 'likely', 5));

        // Defence worst stays zero across the horizon.
        $defence = null;
        foreach ($lanes as $lane) {
            if ($lane->getKey() === 'defence') {
                $defence = $lane;
            }
        }
        $this->assertNotNull($defence);
        for ($year = 1; $year <= 5; $year++) {
            $this->assertSame(0, $defence->getScenarioValue($year, 'worst'));
        }
    }

    #[Test]
    public function seed_data_is_complete_and_dash_clean(): void
    {
        $laneKeys = [];
        foreach (VentureSeed::lanes() as $lane) {
            $laneKeys[] = $lane['key'];
            foreach (VentureLane::SCENARIOS as $scenario) {
                $this->assertCount(VentureLane::YEARS, $lane['grid'][$scenario], $lane['key'] . ' ' . $scenario);
                foreach ($lane['grid'][$scenario] as $value) {
                    $this->assertIsInt($value);
                    $this->assertGreaterThanOrEqual(0, $value);
                }
            }
            $text = $lane['title'] . ' ' . $lane['summary'] . ' ' . implode(' ', $lane['assumptions']);
            $this->assertStringNotContainsString("\u{2014}", $text, 'em dash in lane copy');
            $this->assertStringNotContainsString("\u{2013}", $text, 'en dash in lane copy');
        }
        $this->assertSame(['technology', 'faraday', 'sourcing', 'assessments', 'defence', 'pathways'], $laneKeys);

        foreach (VentureSeed::facts() as $fact) {
            $this->assertContains($fact['lane_key'], $laneKeys, $fact['key'] . ' references a real lane');
            $text = $fact['label'] . ' ' . $fact['detail'];
            $this->assertStringNotContainsString("\u{2014}", $text);
            $this->assertStringNotContainsString("\u{2013}", $text);
        }
    }

    #[Test]
    public function ventures_routes_are_registered(): void
    {
        $router = new WaaseyaaRouter();
        new AnokiiServiceProvider()->routes($router);

        $this->assertSame('anokii.ventures', $router->match('/anokii/ventures')['_route'] ?? null);
        $this->assertSame('anokii.ventures.lane_history', $router->match('/anokii/ventures/lane/technology/history')['_route'] ?? null);
        $this->assertSame('anokii.ventures.fact_history', $router->match('/anokii/ventures/fact/faraday-test-data/history')['_route'] ?? null);

        // The save endpoints are POST-only; match() runs in a GET context, so
        // "method not allowed" (rather than "no such route") proves they are
        // registered on the path with the right method restriction.
        foreach (['/anokii/ventures/lane/save', '/anokii/ventures/fact/save'] as $postPath) {
            try {
                $router->match($postPath);
                $this->fail($postPath . ' should be POST-only');
            } catch (\Waaseyaa\Routing\Exception\RouteMethodNotAllowedException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function signed_out_requests_are_redirected_or_401(): void
    {
        $controller = new VenturesController(null, new VentureService(null), WorkspaceAccess::handler());

        $index = $controller->index(new Request());
        $this->assertInstanceOf(RedirectResponse::class, $index);
        $this->assertSame('/anokii/login', $index->getTargetUrl());

        $save = $controller->saveLane(Request::create('/anokii/ventures/lane/save', 'POST', [], [], [], [], (string) json_encode(['key' => 'technology', 'changes' => ['y1_likely' => 1]])));
        $this->assertSame(401, $save->getStatusCode());

        $fact = $controller->saveFact(Request::create('/anokii/ventures/fact/save', 'POST', [], [], [], [], (string) json_encode(['key' => 'faraday-test-data', 'status' => 'confirmed'])));
        $this->assertSame(401, $fact->getStatusCode());
    }

    #[Test]
    public function ventures_module_is_live_in_the_shell(): void
    {
        $module = Modules::find('ventures');
        $this->assertNotNull($module);
        $this->assertTrue($module['live']);
        $this->assertSame('/anokii/ventures', $module['href']);
        $this->assertTrue($module['tile']);
    }

    #[Test]
    public function ventures_template_renders_chat_first_with_placeholder_banner(): void
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        $this->assertNotNull($twig);

        $lanes = [];
        foreach (VentureSeed::lanes() as $i => $data) {
            $entity = $this->seededLane($data, ($i + 1) * 10);
            $lanes[] = [
                'key' => $entity->getKey(),
                'title' => $entity->getTitle(),
                'summary' => $entity->getSummary(),
                'grid' => $entity->getScenarioGrid(),
                'assumptions' => $entity->getAssumptions(),
                'notes' => '',
                'facts' => $entity->getKey() === 'faraday' ? [[
                    'key' => 'faraday-test-data',
                    'label' => 'Independent test data',
                    'detail' => 'No test data, no government sale.',
                    'status' => 'placeholder',
                    'confirmed_by' => '',
                    'confirmed_at' => '',
                ]] : [],
                'last_edited_by' => '',
                'last_edited_at' => '',
            ];
        }
        $totals = VentureService::rollup(array_map(fn(array $d) => $this->seededLane($d), VentureSeed::lanes()));

        $html = $twig->render('anokii/ventures.html.twig', $this->shell('ventures') + [
            'lanes' => $lanes,
            'rollup' => $totals,
            'tech_share' => 87,
            'snapshot' => ['as_of' => VentureSeed::AS_OF, 'model_version' => VentureSeed::MODEL_VERSION, 'note' => VentureSeed::SNAPSHOT_NOTE],
            'can_edit' => true,
            'can_confirm' => true,
            'scenarios' => VentureLane::SCENARIOS,
            'years' => VentureLane::YEARS,
        ]);

        // The placeholder posture is loud and names the workbook.
        $this->assertStringContainsString('Placeholder grade', $html);
        $this->assertStringContainsString(VentureSeed::MODEL_VERSION, $html);
        $this->assertStringContainsString(VentureSeed::AS_OF, $html);
        // Chat is the primary surface: the Co-Intelligence pane and the lane
        // rail render side by side (the Identity Workspace template).
        $this->assertStringContainsString('class="cowork"', $html);
        $this->assertStringContainsString('Working on the venture numbers with you', $html);
        // Lane cards carry the chat proposal target ids and editable cells.
        $this->assertStringContainsString('id="lane-technology"', $html);
        $this->assertStringContainsString('id="lane-defence"', $html);
        $this->assertStringContainsString('data-field="y5_likely"', $html);
        // Gating facts render status + the confirm control.
        $this->assertStringContainsString('id="fact-faraday-test-data"', $html);
        $this->assertStringContainsString('class="fstat f-placeholder"', $html);
        $this->assertStringContainsString('>Confirm<', $html);
        // Roll-up renders with the formatted likely Yr 5 total.
        $this->assertStringContainsString('3,518,500', $html);
        // Hard rules: no em or en dashes anywhere in the rendered tool.
        $this->assertStringNotContainsString("\u{2014}", $html);
        $this->assertStringNotContainsString("\u{2013}", $html);
        $this->assertStringNotContainsString('{%', $html);
    }

    /** Minimal shell context for template render tests (AnokiiTest pattern). */
    private function shell(string $active): array
    {
        return [
            'nav_active' => $active,
            'modules' => Modules::all(),
            'user_label' => 'Russell',
            'user_role' => 'Editor',
            'user_initials' => 'RU',
        ];
    }
}
