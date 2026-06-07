<?php

declare(strict_types=1);

namespace App\Controller;

use App\Drive\DriveRepository;
use App\Drive\DriveStorage;
use App\Support\AnokiiShell;
use App\Support\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Drive: Nation-shared file storage. Lists files with type icons and a detail
 * side panel, accepts uploads, and streams downloads. Bytes live on the
 * sovereign storage volume via the Waaseyaa media layer (DriveStorage); the
 * drive_file index table carries the listing metadata and per-file attribution.
 *
 * Like the other Anokii tools, routes are registered ->allowAll() and this
 * controller enforces the session itself: page requests redirect to
 * /anokii/login, JSON/file actions return 401.
 */
final class DriveController
{
    public function __construct(
        private readonly ?EntityTypeManager $entityTypeManager,
        private readonly DriveRepository $files,
        private readonly DriveStorage $storage,
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

        $context = AnokiiShell::context($user, 'drive') + [
            'files' => $this->files->all(),
            'folders' => $this->files->folders(),
        ];

        return new Response(
            $twig->render('anokii/drive.html.twig', $context),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    public function upload(Request $request): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Not signed in.'], 401);
        }

        $uploaded = $request->files->get('file');
        if (!$uploaded instanceof UploadedFile || !$uploaded->isValid()) {
            return new JsonResponse(['ok' => false, 'error' => 'No valid file was uploaded.'], 422);
        }

        $originalName = $uploaded->getClientOriginalName();
        $folder = trim((string) $request->request->get('folder', ''));

        try {
            $file = $this->storage->store($uploaded->getPathname(), $originalName, $user->id());
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable) {
            return new JsonResponse(['ok' => false, 'error' => 'Could not store the file.'], 500);
        }

        $row = $this->files->create(
            name: $originalName,
            mimeType: $file->mimeType,
            sizeBytes: $file->size,
            ownerId: $user->id(),
            ownerLabel: Auth::label($user),
            folder: $folder,
            storageUri: $file->uri,
        );

        return new JsonResponse(['ok' => true, 'file' => $this->present($row)]);
    }

    /**
     * Stream a stored file. Inline by default (so images render in the panel),
     * forced as an attachment when ?dl=1 is present.
     */
    public function download(Request $request, string $id): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new RedirectResponse('/anokii/login');
        }

        $row = $this->files->find($id);
        if ($row === null) {
            return new Response('Not found', 404);
        }

        $path = $this->storage->pathForUri((string) $row['storage_uri']);
        if ($path === null) {
            return new Response('File is missing from storage.', 404);
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', (string) $row['mime_type']);
        $disposition = $request->query->getBoolean('dl')
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE;
        $response->setContentDisposition($disposition, (string) $row['name']);

        return $response;
    }

    public function delete(Request $request): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Not signed in.'], 401);
        }

        $decoded = json_decode((string) $request->getContent(), true);
        $data = is_array($decoded) ? $decoded : [];
        $uuid = trim((string) ($data['uuid'] ?? ''));
        if ($uuid === '') {
            return new JsonResponse(['ok' => false, 'error' => 'Missing file id.'], 422);
        }

        $uri = $this->files->delete($uuid);
        if ($uri === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Unknown file.'], 404);
        }
        $this->storage->delete($uri);

        return new JsonResponse(['ok' => true]);
    }

    /**
     * Shape a row for the client (add the view/download URLs).
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function present(array $row): array
    {
        $uuid = (string) ($row['uuid'] ?? '');
        $row['view_url'] = '/anokii/drive/file/' . $uuid;
        $row['download_url'] = '/anokii/drive/file/' . $uuid . '?dl=1';

        return $row;
    }
}
