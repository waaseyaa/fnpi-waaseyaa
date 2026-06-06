<?php

declare(strict_types=1);

namespace App\Controller;

use App\Identity\IdentitySeed;
use App\Identity\PillarRepository;
use App\Support\AnokiiShell;
use App\Support\Auth;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Tool #1: the Identity Workspace. Renders the seeded pillars grouped by
 * section with a computed maturity bar, and persists status + notes edits to
 * the database (shared across accounts), stamping last-edited-by/at.
 */
final class IdentityController
{
    public function __construct(
        private readonly ?EntityTypeManager $entityTypeManager,
        private readonly PillarRepository $pillars,
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new RedirectResponse('/anokii/login');
        }

        $all = $this->pillars->all();

        // Group pillars by section, preserving the seed section order.
        $sections = [];
        foreach (IdentitySeed::sections() as $key => $meta) {
            $sections[$key] = $meta + ['key' => $key, 'pillars' => []];
        }
        foreach ($all as $p) {
            $key = (string) $p['section'];
            if (isset($sections[$key])) {
                $sections[$key]['pillars'][] = $p;
            }
        }

        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            return new Response('Anokii unavailable: Twig is not initialised.', 500);
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

        $status = array_key_exists('status', $data) ? (string) $data['status'] : null;
        $notes = array_key_exists('notes', $data) ? (string) $data['notes'] : null;

        $result = $this->pillars->update($pid, $status, $notes, Auth::label($user));
        if ($result === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Unknown pillar or nothing to update.'], 422);
        }

        return new JsonResponse([
            'ok' => true,
            'last_edited_by' => $result['last_edited_by'],
            'last_edited_at' => $result['last_edited_at'],
            'counts' => $this->pillars->statusCounts(),
        ]);
    }
}
