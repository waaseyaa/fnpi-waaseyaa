<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GatingFact;
use App\Entity\VentureLane;
use App\Support\AnokiiShell;
use Anokii\Support\Auth;
use App\Venture\VentureService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * The Venture Numbers section: the revenue model mirrored lane by lane, staff
 * only. Chat is the primary surface (the lane cards are the right work rail
 * beside the Co-Intelligence pane, the Identity Workspace template); the grid,
 * assumptions, and gating facts are also directly editable so a non-developer
 * maintains the numbers without touching the agent.
 *
 * Unlike the other tools, READING this section is permission-gated: the
 * VentureAccessPolicy requires `view ventures` (Forbidden otherwise), so a
 * signed-in account without it gets a 403 here and no rows anywhere else.
 * Routes are registered ->allowAll(); this controller enforces the session
 * (page requests redirect to /anokii/login, JSON actions return 401).
 */
final class VenturesController
{
    public function __construct(
        private readonly ?EntityTypeManager $entityTypeManager,
        private readonly VentureService $ventures,
        private readonly EntityAccessHandler $access,
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new RedirectResponse('/anokii/login');
        }
        if (!$this->access->check(new VentureLane(), 'view', $user)->isAllowed()) {
            return new Response('Venture numbers are staff-only.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            return new Response('Anokii unavailable: Twig is not initialised.', 500);
        }

        $lanes = $this->ventures->listLanes();
        $factsByLane = $this->ventures->factsByLane();
        $totals = VentureService::rollup($lanes);
        $snapshot = $this->ventures->snapshot();

        $presented = [];
        $techShare = 0;
        foreach ($lanes as $lane) {
            $presented[] = $this->presentLane($lane, $factsByLane[$lane->getKey()] ?? []);
            if ($lane->getKey() === 'technology') {
                $techShare = VentureService::shareOfYear($totals, $lane, 'likely', VentureLane::YEARS);
            }
        }

        $context = AnokiiShell::context($user, 'ventures') + [
            'lanes' => $presented,
            'rollup' => $totals,
            'tech_share' => $techShare,
            'snapshot' => [
                'as_of' => $snapshot?->getAsOf() ?? '',
                'model_version' => $snapshot?->getModelVersion() ?? '',
                'note' => $snapshot?->getNote() ?? '',
            ],
            'can_edit' => $this->access->check(new VentureLane(), 'update', $user)->isAllowed(),
            'can_confirm' => $this->access->check(new GatingFact(), 'confirm', $user)->isAllowed(),
            'scenarios' => VentureLane::SCENARIOS,
            'years' => VentureLane::YEARS,
        ];

        return new Response(
            $twig->render('anokii/ventures.html.twig', $context),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    /** Save scenario values / summary / notes / assumptions on one lane. */
    public function saveLane(Request $request): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Not signed in.'], 401);
        }

        $decoded = json_decode((string) $request->getContent(), true);
        $data = is_array($decoded) ? $decoded : [];
        $key = trim((string) ($data['key'] ?? ''));
        if ($key === '') {
            return new JsonResponse(['ok' => false, 'error' => 'Missing lane key.'], 422);
        }

        $lane = $this->ventures->findLaneByKey($key);
        if ($lane === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Unknown lane.'], 422);
        }
        if (!$this->access->check($lane, 'update', $user)->isAllowed()) {
            return new JsonResponse(['ok' => false, 'error' => 'You do not have permission to edit the venture numbers.'], 403);
        }

        $changes = is_array($data['changes'] ?? null) ? $data['changes'] : [];
        $result = $this->ventures->updateLane($key, $changes, Auth::label($user));
        if ($result === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Nothing to update, or a value was not a whole non-negative number.'], 422);
        }

        $totals = VentureService::rollup($this->ventures->listLanes());

        return new JsonResponse([
            'ok' => true,
            'changed' => $result['changed'],
            'last_edited_by' => $result['editor_label'],
            'last_edited_at' => $this->humanStamp($result['updated_at']),
            'rollup' => $totals,
        ]);
    }

    /** Flip a gating fact's status (confirm / back to placeholder) or edit its detail. */
    public function saveFact(Request $request): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Not signed in.'], 401);
        }

        $decoded = json_decode((string) $request->getContent(), true);
        $data = is_array($decoded) ? $decoded : [];
        $key = trim((string) ($data['key'] ?? ''));
        if ($key === '') {
            return new JsonResponse(['ok' => false, 'error' => 'Missing fact key.'], 422);
        }

        $fact = $this->ventures->findFactByKey($key);
        if ($fact === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Unknown gating fact.'], 422);
        }

        $newStatus = array_key_exists('status', $data) ? (string) $data['status'] : null;
        $detail = array_key_exists('detail', $data) ? (string) $data['detail'] : null;

        // A status flip is the confirmation act and carries its own gate; a
        // detail edit is an ordinary update.
        $operation = $newStatus !== null && $newStatus !== $fact->getStatus() ? 'confirm' : 'update';
        if (!$this->access->check($fact, $operation, $user)->isAllowed()) {
            return new JsonResponse(['ok' => false, 'error' => 'You do not have permission to change this gating fact.'], 403);
        }

        $result = $this->ventures->updateFact($key, $newStatus, $detail, $user->id(), Auth::label($user));
        if ($result === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Nothing to update, or the status was invalid.'], 422);
        }

        return new JsonResponse([
            'ok' => true,
            'status' => $result['status'],
            'confirmed_by' => $result['confirmed_by'],
            'confirmed_at' => $this->humanStamp($result['confirmed_at']),
            'last_edited_by' => $result['editor_label'],
            'last_edited_at' => $this->humanStamp($result['updated_at']),
        ]);
    }

    /** Per-lane revision history for the history panel. */
    public function laneHistory(Request $request, string $key): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Not signed in.'], 401);
        }
        if (!$this->access->check(new VentureLane(), 'view', $user)->isAllowed()) {
            return new JsonResponse(['ok' => false, 'error' => 'Venture numbers are staff-only.'], 403);
        }

        $revisions = [];
        foreach ($this->ventures->listLaneHistory($key) as $rev) {
            $revisions[] = [
                'vid' => (int) $rev->getRevisionId(),
                'summary' => $rev->getRevisionLog(),
                'editor' => $rev->getEditorLabel() !== '' ? $rev->getEditorLabel() : 'System',
                // revision_author (framework, alpha.205+), falling back to the
                // editor_uid snapshot old revisions carry in _data.
                'editor_uid' => $rev->getEditorUid(),
                'when' => $this->revisionStamp($rev->getUpdatedAt(), $rev->getRevisionCreatedAt()),
                'is_current' => $rev->isCurrentRevision(),
            ];
        }
        if ($revisions === []) {
            return new JsonResponse(['ok' => false, 'error' => 'Unknown lane.'], 404);
        }

        return new JsonResponse(['ok' => true, 'revisions' => $revisions]);
    }

    /** Per-fact revision history (the placeholder-to-confirmed trail). */
    public function factHistory(Request $request, string $key): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Not signed in.'], 401);
        }
        if (!$this->access->check(new GatingFact(), 'view', $user)->isAllowed()) {
            return new JsonResponse(['ok' => false, 'error' => 'Venture numbers are staff-only.'], 403);
        }

        $revisions = [];
        foreach ($this->ventures->listFactHistory($key) as $rev) {
            $revisions[] = [
                'vid' => (int) $rev->getRevisionId(),
                'status' => $rev->getStatus(),
                'summary' => $rev->getRevisionLog(),
                'editor' => $rev->getEditorLabel() !== '' ? $rev->getEditorLabel() : 'System',
                // revision_author (framework, alpha.205+), falling back to the
                // editor_uid snapshot old revisions carry in _data.
                'editor_uid' => $rev->getEditorUid(),
                'when' => $this->revisionStamp($rev->getUpdatedAt(), $rev->getRevisionCreatedAt()),
                'is_current' => $rev->isCurrentRevision(),
            ];
        }
        if ($revisions === []) {
            return new JsonResponse(['ok' => false, 'error' => 'Unknown gating fact.'], 404);
        }

        return new JsonResponse(['ok' => true, 'revisions' => $revisions]);
    }

    /**
     * @param list<GatingFact> $facts
     * @return array<string,mixed>
     */
    private function presentLane(VentureLane $lane, array $facts): array
    {
        $presentedFacts = [];
        foreach ($facts as $fact) {
            $presentedFacts[] = [
                'key' => $fact->getKey(),
                'label' => $fact->getLabel(),
                'detail' => $fact->getDetail(),
                'status' => $fact->getStatus(),
                'confirmed_by' => $fact->getConfirmedByLabel(),
                'confirmed_at' => $this->humanStamp($fact->getConfirmedAt()),
            ];
        }

        return [
            'key' => $lane->getKey(),
            'title' => $lane->getTitle(),
            'summary' => $lane->getSummary(),
            'grid' => $lane->getScenarioGrid(),
            'assumptions' => $lane->getAssumptions(),
            'notes' => $lane->getNotes(),
            'facts' => $presentedFacts,
            'last_edited_by' => $lane->getEditorLabel(),
            'last_edited_at' => $this->humanStamp($lane->getUpdatedAt()),
        ];
    }

    /**
     * History "when": prefer the app edit stamp, fall back to revision
     * metadata, never render a bare " UTC" (the Identity pattern).
     */
    private function revisionStamp(string $updatedAt, ?\DateTimeImmutable $created): string
    {
        if (trim($updatedAt) !== '') {
            return $this->humanStamp($updatedAt);
        }

        return $created !== null ? $created->format('M j, Y g:i A') . ' UTC' : '';
    }

    private function humanStamp(string $iso): string
    {
        $iso = trim($iso);
        if ($iso === '') {
            return '';
        }
        $ts = strtotime($iso);

        return $ts === false ? $iso : gmdate('M j, Y g:i A', $ts) . ' UTC';
    }
}
