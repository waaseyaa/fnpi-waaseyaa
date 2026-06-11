<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contact\ContactRateLimiter;
use App\Entity\ContactSubmission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * The public contact-form POST endpoint.
 *
 * Email is the only required field (format-validated server-side); name,
 * organization, topic, and message are optional. Spam hygiene without
 * third-party vendors: a honeypot field, a minimum-fill-time trap, and
 * per-sender rate limiting (salted IP hash, no raw IP retained). Honeypot,
 * too-fast, and over-limit posts all render the SUCCESS page without storing
 * anything, so abusers learn nothing. CSRF follows the framework pattern: the
 * form carries the session token as a hidden field (no route exemption).
 *
 * Storage is the contact_submission entity; staff read it in the Anokii
 * Inbox. Writes happen here, server-side only; the entity gate never grants
 * create (see ContactSubmissionAccessPolicy).
 */
final class ContactSubmitController
{
    /** Seconds a human plausibly needs to fill the form. */
    public const MIN_FILL_SECONDS = 4;

    /** Oldest acceptable form render; staleness beyond this is a reload case, not spam. */
    private const MAX_FORM_AGE_SECONDS = 86400;

    private const TOPICS = ['technology', 'sourcing', 'protection', 'defence', 'partnership'];

    public function __construct(
        private readonly ?EntityRepositoryInterface $submissions,
        private readonly ?ContactRateLimiter $rateLimiter,
    ) {}

    public function submit(Request $request): Response
    {
        if ($this->submissions === null) {
            return new Response('Contact form unavailable.', 503);
        }

        $post = $request->request;
        $old = [
            'name' => $this->cap((string) $post->get('name', ''), 200),
            'organization' => $this->cap((string) $post->get('organization', ''), 200),
            'email' => $this->cap((string) $post->get('email', ''), 254),
            'topic' => (string) $post->get('topic', ''),
            'message' => $this->cap((string) $post->get('message', ''), 5000),
        ];

        // Honeypot: a real visitor never sees or fills this field. Render the
        // success page, store nothing, learn nothing.
        if (trim((string) $post->get('website', '')) !== '') {
            return $this->render(['form_success' => true]);
        }

        // Minimum fill time: the form stamps its render time. A post faster
        // than a human can type, or carrying a forged/future stamp, is dropped
        // silently. A stale stamp (old tab) passes: it can only make the fill
        // time look longer.
        $fts = (string) $post->get('fts', '');
        if (!ctype_digit($fts)) {
            return $this->render(['form_success' => true]);
        }
        $age = time() - (int) $fts;
        if ($age < self::MIN_FILL_SECONDS || $age > self::MAX_FORM_AGE_SECONDS) {
            return $this->render(['form_success' => true]);
        }

        // Per-sender rate limit (salted hash, no raw IP). Silent as well.
        if ($this->rateLimiter !== null && !$this->rateLimiter->allow($this->rateLimiter->ipHash($request->getClientIp()))) {
            return $this->render(['form_success' => true]);
        }

        // The one hard requirement: a well-formed email address.
        $email = trim($old['email']);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->render([
                'form_error' => 'Please enter a working email address so we can come back to you. Everything else is optional.',
                'form_old' => $old,
            ], 422);
        }

        $submission = new ContactSubmission();
        $submission->set('uuid', Uuid::v4()->toRfc4122());
        $submission->fill(
            $email,
            trim($old['name']),
            trim($old['organization']),
            in_array($old['topic'], self::TOPICS, true) ? $old['topic'] : '',
            trim($old['message']),
            gmdate('Y-m-d\TH:i:s\Z'),
            '/contact',
        );
        $submission->enforceIsNew();
        $this->submissions->save($submission);

        return $this->render(['form_success' => true]);
    }

    /** @param array<string, mixed> $context */
    private function render(array $context, int $status = 200): Response
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            // No SSR (CLI edge case): still acknowledge honestly.
            return new Response(isset($context['form_success']) ? 'Got it.' : 'Please check the form and try again.', $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return new Response($twig->render('contact-result.html.twig', $context), $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function cap(string $value, int $max): string
    {
        return mb_substr($value, 0, $max);
    }
}
