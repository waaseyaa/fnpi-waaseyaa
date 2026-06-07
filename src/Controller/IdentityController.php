<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Pillar;
use App\Identity\IdentitySeed;
use App\Identity\PillarService;
use App\Support\AnokiiShell;
use App\Support\Auth;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Tool #1: the Identity Workspace, entity-native rebuild. Renders the pillars
 * grouped by section with a computed maturity bar, and persists status + notes
 * edits as revisions of the `identity_pillar` entity (full per-pillar history,
 * attribution). Status and notes are the editable fields; the rest of a pillar
 * is read-only here (the chat agent will CRUD it next).
 *
 * Routes are registered ->allowAll() and this controller enforces the session:
 * page requests redirect to /anokii/login, JSON actions return 401.
 */
final class IdentityController
{
    public function __construct(
        private readonly ?EntityTypeManager $entityTypeManager,
        private readonly PillarService $pillars,
        private readonly EntityAccessHandler $access,
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new RedirectResponse('/anokii/login');
        }

        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            return new Response('Anokii unavailable: Twig is not initialised.', 500);
        }

        // Group pillars by section, preserving the section order.
        $sections = [];
        foreach (IdentitySeed::sections() as $key => $meta) {
            $sections[$key] = $meta + ['key' => $key, 'pillars' => []];
        }
        foreach ($this->pillars->listPillars() as $pillar) {
            $key = $pillar->getSection();
            if (isset($sections[$key])) {
                $sections[$key]['pillars'][] = $this->presentPillar($pillar);
            }
        }

        $context = AnokiiShell::context($user, 'identity') + [
            'sections' => array_values($sections),
            'counts' => $this->pillars->statusCounts(),
            'statuses' => [
                ['v' => 'defined', 't' => 'Defined'],
                ['v' => 'draft', 't' => 'Draft / legacy'],
                ['v' => 'work', 't' => 'Needs work'],
                ['v' => 'gap', 't' => 'Gap'],
            ],
        ];

        return new Response(
            $twig->render('anokii/identity.html.twig', $context),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    public function save(Request $request): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Not signed in.'], 401);
        }

        $decoded = json_decode((string) $request->getContent(), true);
        $data = is_array($decoded) ? $decoded : [];

        $pid = trim((string) ($data['pid'] ?? ''));
        if ($pid === '') {
            return new JsonResponse(['ok' => false, 'error' => 'Missing pillar id.'], 422);
        }

        // Access: the pillar must exist, and the account must be allowed to edit
        // it (the AccessPolicy is the single source of truth).
        $pillar = $this->pillars->findByPid($pid);
        if ($pillar === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Unknown pillar.'], 422);
        }
        if (!$this->access->check($pillar, 'update', $user)->isAllowed()) {
            return new JsonResponse(['ok' => false, 'error' => 'You do not have permission to edit the Identity Workspace.'], 403);
        }

        $status = array_key_exists('status', $data) ? (string) $data['status'] : null;
        $notes = array_key_exists('notes', $data) ? (string) $data['notes'] : null;

        $result = $this->pillars->update($pid, $status, $notes, $user->id(), Auth::label($user));
        if ($result === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Unknown pillar or nothing to update.'], 422);
        }

        return new JsonResponse([
            'ok' => true,
            'last_edited_by' => $result['editor_label'],
            'last_edited_at' => $this->humanStamp($result['updated_at']),
            'counts' => $this->pillars->statusCounts(),
        ]);
    }

    /** Per-pillar revision history (who changed what, when) for the history panel. */
    public function history(Request $request, string $pid): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Not signed in.'], 401);
        }

        $revisions = [];
        foreach ($this->pillars->listHistory($pid) as $rev) {
            $revisions[] = $this->presentRevision($rev);
        }
        if ($revisions === []) {
            return new JsonResponse(['ok' => false, 'error' => 'Unknown pillar.'], 404);
        }

        return new JsonResponse(['ok' => true, 'revisions' => $revisions]);
    }

    /** @return array<string,mixed> */
    private function presentPillar(Pillar $pillar): array
    {
        return [
            'pid' => $pillar->getPid(),
            'section' => $pillar->getSection(),
            'title' => $pillar->getTitle(),
            'now_label' => $pillar->getNowLabel(),
            'body' => $pillar->getBody(),
            'is_quote' => $pillar->isQuote(),
            'decide_label' => $pillar->getDecideLabel(),
            'decision' => $pillar->getDecision(),
            'status' => $pillar->getStatus(),
            'notes' => $pillar->getNotes(),
            'pills' => $pillar->getPills(),
            'is_full' => $pillar->isFull(),
            'last_edited_by' => $pillar->getEditorLabel(),
            'last_edited_at' => $this->humanStamp($pillar->getUpdatedAt()),
        ];
    }

    /** @return array<string,mixed> */
    private function presentRevision(Pillar $rev): array
    {
        $created = $rev->getRevisionCreatedAt();
        // Prefer the preserved edit stamp (faithful across the migration); fall
        // back to the revision metadata time.
        $when = $rev->getUpdatedAt() !== ''
            ? $this->humanStamp($rev->getUpdatedAt())
            : ($created?->format('M j, Y g:i A') . ' UTC');

        return [
            'vid' => (int) $rev->getRevisionId(),
            'status' => $rev->getStatus(),
            'summary' => $rev->getRevisionLog(),
            'editor' => $rev->getEditorLabel() !== '' ? $rev->getEditorLabel() : 'System',
            'when' => $when,
            'is_current' => $rev->isCurrentRevision(),
        ];
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
