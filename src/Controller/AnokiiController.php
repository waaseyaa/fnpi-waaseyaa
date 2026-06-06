<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\SetupTokenRepository;
use App\Support\Auth;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Auth\AuthManager;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrServiceProvider;
use Waaseyaa\User\User;

/**
 * The Anokii authed shell: login, logout, dashboard, settings, and the
 * token-based set-password flow. Everything except /anokii/login and
 * /anokii/set-password requires a session; the guard redirects to
 * /anokii/login (page) or returns 401 (JSON action).
 */
final class AnokiiController
{
    public function __construct(
        private readonly ?EntityTypeManager $entityTypeManager,
        private readonly SetupTokenRepository $tokens,
    ) {}

    // --- shell pages -------------------------------------------------------

    public function dashboard(Request $request): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new RedirectResponse('/anokii/login');
        }

        return $this->render('anokii/home.html.twig', [
            'nav_active' => 'home',
            'user_label' => Auth::label($user),
        ]);
    }

    public function settings(Request $request): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new RedirectResponse('/anokii/login');
        }

        return $this->render('anokii/settings.html.twig', [
            'nav_active' => 'settings',
            'user_label' => Auth::label($user),
            'profile_name' => $user->getName(),
            'profile_email' => $user->getEmail(),
        ]);
    }

    public function settingsSave(Request $request): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
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
        if (Auth::currentUser($this->entityTypeManager) !== null) {
            return new RedirectResponse('/anokii');
        }

        return $this->render('anokii/login.html.twig', ['bare' => true]);
    }

    public function loginSubmit(Request $request): Response
    {
        $data = $this->json($request);
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $user = $this->userByEmail($email);
        $auth = new AuthManager();
        if ($user === null || !$auth->authenticate($user, $password)) {
            return new JsonResponse(['ok' => false, 'error' => 'Wrong email or password.'], 401);
        }

        $auth->login($user);

        return new JsonResponse(['ok' => true, 'redirect' => '/anokii']);
    }

    public function logout(Request $request): Response
    {
        new AuthManager()->logout();

        return new RedirectResponse('/anokii/login');
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

        $user = $this->userByEmail($email);
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'No account found for this link.'], 404);
        }

        $this->entityTypeManager?->getStorage('user')->save($user->setRawPassword($password));
        $this->tokens->consume($token);

        return new JsonResponse(['ok' => true, 'redirect' => '/anokii/login']);
    }

    // --- helpers -----------------------------------------------------------

    private function userByEmail(string $email): ?User
    {
        if ($email === '' || $this->entityTypeManager === null) {
            return null;
        }
        try {
            $user = $this->entityTypeManager->getStorage('user')->loadByKey('mail', $email);
        } catch (\Throwable) {
            return null;
        }

        return $user instanceof User ? $user : null;
    }

    /**
     * @return array<string,mixed>
     */
    private function json(Request $request): array
    {
        $decoded = json_decode((string) $request->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $context
     */
    private function render(string $name, array $context = []): Response
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            return new Response('Anokii unavailable: Twig is not initialised.', 500);
        }

        return new Response($twig->render($name, $context), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
