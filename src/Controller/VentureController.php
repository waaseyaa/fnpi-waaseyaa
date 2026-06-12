<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\VentureItem;
use App\Entity\VentureThread;
use App\Support\AnokiiShell;
use App\Support\Auth;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * The Venture Tracker workspace surface: the staff working board, rendered as
 * threads (cards) of status-dot items. Read-only v1 -- edits come via the MCP
 * agent or admin tooling; this controller only reads. Staff-only: signed-out
 * requests redirect to login, and the entity access posture
 * ({@see VentureTrackerAccessPolicy}) keeps the types off every public surface.
 */
final class VentureController
{
    public function __construct(private readonly ?EntityTypeManager $entityTypeManager) {}

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

        $itemsByThread = [];
        foreach ($this->repo('venture_item')->findBy([]) as $item) {
            if ($item instanceof VentureItem) {
                $itemsByThread[$item->getThreadUuid()][] = [
                    'body' => $item->getBody(),
                    'status' => $item->getStatus(),
                    'who' => $item->getWho(),
                    'sort_order' => $item->getSortOrder(),
                ];
            }
        }
        foreach ($itemsByThread as &$list) {
            usort($list, static fn(array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);
        }
        unset($list);

        $threads = [];
        foreach ($this->repo('venture_thread')->findBy([]) as $thread) {
            if (!$thread instanceof VentureThread) {
                continue;
            }
            $uuid = (string) $thread->get('uuid');
            $threads[] = [
                'title' => $thread->getTitle(),
                'next' => $thread->getNext(),
                'sort_order' => $thread->getSortOrder(),
                'items' => $itemsByThread[$uuid] ?? [],
            ];
        }
        usort($threads, static fn(array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

        $context = AnokiiShell::context($user, 'venture') + ['threads' => $threads];

        return new Response($twig->render('anokii/venture.html.twig', $context), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function repo(string $type): \Waaseyaa\Entity\Repository\EntityRepositoryInterface
    {
        if ($this->entityTypeManager === null) {
            throw new \LogicException('Venture tracker requires a booted kernel (EntityTypeManager).');
        }

        return $this->entityTypeManager->getRepository($type);
    }
}
