<?php

declare(strict_types=1);

namespace App\Controller;

use App\Analytics\AnalyticsReport;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Renders the analytics dashboard.
 *
 * At the app layer this route is public; it is gated in production by Caddy
 * basic auth on /admin/* (see waaseyaa-infra) where configured. The Twig
 * environment is fetched at request time to avoid boot-order coupling with the
 * SSR provider. Ported from oiatc-waaseyaa, minus the chat-gaps panel.
 */
final class AnalyticsDashboardController
{
    public function __construct(private readonly AnalyticsReport $report) {}

    public function index(Request $request): Response
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            return new Response('Analytics dashboard unavailable: Twig is not initialised.', 500);
        }

        $today = gmdate('Y-m-d');
        $from = $this->cleanDate($request->query->get('from'), gmdate('Y-m-d', strtotime('-29 days')));
        $to = $this->cleanDate($request->query->get('to'), $today);

        $html = $twig->render('admin/analytics.html.twig', [
            'report' => $this->report->summary($from, $to),
            'range' => ['from' => $from, 'to' => $to],
        ]);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function cleanDate(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            ? $value
            : $fallback;
    }
}
