<?php

declare(strict_types=1);

namespace App\Controller;

use Anokii\Dashboard\DashboardGate;
use App\Support\AnokiiShell;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * The Anokii authed workspace shell: dashboard, settings, and the coming-soon
 * placeholder. The login / logout / one-time set-password surface now comes from
 * the shared package ({@see \Anokii\Dashboard\WorkspaceLoginController} +
 * \Anokii\Admin\InviteHandler + \Anokii\Auth\SetupTokenRepository), wired in
 * AnokiiServiceProvider; this controller owns only the FNPI workspace pages.
 *
 * Extends the shared Anokii {@see DashboardGate}: the login-gated split, the
 * session helpers (currentUser), and the Twig-render / JSON-decode helpers all
 * come from the base. Unauthenticated page requests redirect to
 * /admin/anokii/login; JSON actions return 401.
 */
final class AnokiiController extends DashboardGate
{
    public function __construct(
        ?EntityTypeManager $entityTypeManager,
    ) {
        parent::__construct($entityTypeManager);
    }

    protected function loginPath(): string
    {
        return '/admin/anokii/login';
    }

    // --- shell pages -------------------------------------------------------

    public function dashboard(Request $request): Response
    {
        $user = $this->currentUser();
        if ($user === null) {
            return new RedirectResponse($this->loginPath());
        }

        return $this->render('anokii/home.html.twig', AnokiiShell::context($user, 'home'));
    }

    public function comingSoon(Request $request, string $module): Response
    {
        $user = $this->currentUser();
        if ($user === null) {
            return new RedirectResponse($this->loginPath());
        }
        $m = AnokiiShell::find($module);
        if ($m === null || $m['live'] === true) {
            return new RedirectResponse('/admin/anokii');
        }

        return $this->render('anokii/coming-soon.html.twig', AnokiiShell::context($user, $module) + ['module' => $m]);
    }

    public function settings(Request $request): Response
    {
        $user = $this->currentUser();
        if ($user === null) {
            return new RedirectResponse($this->loginPath());
        }

        return $this->render('anokii/settings.html.twig', AnokiiShell::context($user, 'settings') + [
            'profile_name' => $user->getName(),
            'profile_email' => $user->getEmail(),
        ]);
    }

    public function settingsSave(Request $request): Response
    {
        $denied = $this->requireAction();
        if ($denied !== null) {
            return $denied;
        }
        $user = $this->currentUser();
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Not signed in.'], 401);
        }
        $data = $this->json($request);

        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $current = (string) ($data['current_password'] ?? '');
        $new = (string) ($data['new_password'] ?? '');

        $updated = $user;
        if ($name !== '') {
            $updated = $updated->setName($name);
        }
        if ($email !== '') {
            $updated = $updated->setEmail($email);
        }

        if ($new !== '') {
            if (strlen($new) < 10) {
                return new JsonResponse(['ok' => false, 'error' => 'New password must be at least 10 characters.'], 422);
            }
            if (!$user->checkPassword($current)) {
                return new JsonResponse(['ok' => false, 'error' => 'Current password is incorrect.'], 422);
            }
            $updated = $updated->setRawPassword($new);
        }

        $this->entityTypeManager?->getStorage('user')->save($updated);

        return new JsonResponse(['ok' => true]);
    }
}
