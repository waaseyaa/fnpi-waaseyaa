<?php

declare(strict_types=1);

namespace App\Controller;

use App\Analytics\AnalyticsReport;
use App\Support\AnokiiShell;
use App\Support\Auth;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * First-party analytics inside the Anokii workspace: the same self-hosted,
 * cookieless report (pageviews, visitors, top pages, referrers, devices from
 * FNPI's own SQLite; no third party anywhere), now staff-gated where Russell
 * and Matthew already work. Replaces the old standalone /admin/analytics
 * page, which relied on an edge basic-auth gate that was never configured
 * and therefore sat publicly reachable; that path now redirects here.
 */
final class AnokiiAnalyticsController
{
    public function __construct(
        private readonly ?EntityTypeManager $entityTypeManager,
        private readonly AnalyticsReport $report,
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

        $from = $this->cleanDate($request->query->get('from'), gmdate('Y-m-d', strtotime('-29 days')));
        $to = $this->cleanDate($request->query->get('to'), gmdate('Y-m-d'));

        $context = AnokiiShell::context($user, 'analytics') + [
            'report' => $this->report->summary($from, $to),
            'range' => ['from' => $from, 'to' => $to],
        ];

        return new Response($twig->render('anokii/analytics.html.twig', $context), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function cleanDate(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            ? $value
            : $fallback;
    }
}
