<?php

declare(strict_types=1);

namespace App\Controller;

use Anokii\Dashboard\DashboardGate;
use Anokii\Support\Auth;
use App\Anokii\Modules;
use App\Auth\SetupTokenRepository;
use App\Support\AnokiiShell;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * The Anokii authed shell: login, logout, dashboard, settings, and the
 * token-based set-password flow.
 *
 * Extends the shared Anokii {@see DashboardGate}: the public-open / login-gated
 * split, the session helpers (currentUser), and the Twig-render / JSON-decode
 * helpers all come from the base. This controller owns only the FNPI-specific
 * handler bodies and the token set-password flow. The gate redirects
 * unauthenticated page requests to /admin/anokii/login and returns 401 for JSON
 * actions.
 */
final class AnokiiController extends DashboardGate
{
    public function __construct(
        ?EntityTypeManager $entityTypeManager,
        private readonly SetupTokenRepository $tokens,
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
        $m = Modules::find($module);
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

    // --- login / logout ----------------------------------------------------

    public function loginForm(Request $request): Response
    {
        if ($this->currentUser() !== null) {
            return new RedirectResponse('/admin/anokii');
        }

        return $this->render('anokii/login.html.twig', ['bare' => true]);
    }

    public function loginSubmit(Request $request): Response
    {
        $data = $this->json($request);
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $user = Auth::login($this->entityTypeManager, $email, $password);
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Wrong email or password.'], 401);
        }

        return new JsonResponse(['ok' => true, 'redirect' => '/admin/anokii']);
    }

    public function logout(Request $request): Response
    {
        Auth::logout();

        return new RedirectResponse('/admin/anokii/login');
    }

    // --- token set-password flow ------------------------------------------

    public function setPasswordForm(Request $request): Response
    {
        $token = (string) $request->query->get('token', '');
        $email = $this->tokens->emailForToken($token);

        return $this->render('anokii/set-password.html.twig', [
            'bare' => true,
            'valid' => $email !== null,
            'email' => $email ?? '',
            'token' => $token,
        ]);
    }

    public function setPasswordSubmit(Request $request): Response
    {
        $data = $this->json($request);
        $token = (string) ($data['token'] ?? '');
        $password = (string) ($data['password'] ?? '');

        if (strlen($password) < 10) {
            return new JsonResponse(['ok' => false, 'error' => 'Password must be at least 10 characters.'], 422);
        }

        $email = $this->tokens->emailForToken($token);
        if ($email === null) {
            return new JsonResponse(['ok' => false, 'error' => 'This link is invalid or has already been used.'], 410);
        }

        $user = Auth::userByEmail($this->entityTypeManager, $email);
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'No account found for this link.'], 404);
        }

        $this->entityTypeManager?->getStorage('user')->save($user->setRawPassword($password));
        $this->tokens->consume($token);

        return new JsonResponse(['ok' => true, 'redirect' => '/admin/anokii/login']);
    }
}
