<?php

declare(strict_types=1);

namespace App\Command;

use App\Drive\DriveFileService;
use App\Drive\DriveStorage;
use App\Drive\FileTypes;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\User\User;

/**
 * `vendor/bin/waaseyaa app:seed-drive --dir=... [--folder=] [--owner-email=] [--owner-id=] [--owner-label=]`
 *
 * Imports a directory of images into Drive: bytes go onto the sovereign volume
 * through the media layer (DriveStorage), and an index row is written per file.
 * Idempotent: a file already present in the target folder (by name) is skipped,
 * so a re-run converges rather than duplicating. Used to seed Matthew's global
 * relationship photos under the "Global relationships" folder.
 */
final class SeedDriveCommand
{
    private const array IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct(
        private readonly DriveFileService $files,
        private readonly DriveStorage $storage,
        private readonly ?EntityTypeManagerInterface $entityTypeManager,
    ) {}

    public function run(CliIO $io): int
    {
        $dir = rtrim((string) ($io->option('dir') ?? ''), '/\\');
        if ($dir === '' || !is_dir($dir)) {
            $io->error('Provide a readable directory: --dir=/path/to/images');

            return 1;
        }

        $folder = trim((string) ($io->option('folder') ?? '')) ?: 'Global relationships';
        [$ownerId, $ownerLabel] = $this->resolveOwner($io);

        // Existing names in this folder, for idempotent re-runs.
        $existing = [];
        foreach ($this->files->listFiles() as $existingFile) {
            if ($existingFile->getFolder() === $folder) {
                $existing[$existingFile->getName()] = true;
            }
        }

        $added = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->imageFiles($dir) as $path) {
            $name = basename($path);
            if (isset($existing[$name])) {
                $io->writeln(sprintf('  skip   %s (already in "%s")', $name, $folder));
                $skipped++;
                continue;
            }

            try {
                $file = $this->storage->store($path, $name, $ownerId);
                $now = gmdate('Y-m-d H:i:s');
                $this->files->createFile(
                    name: $name,
                    mimeType: $file->mimeType,
                    kind: FileTypes::kind($file->mimeType, $name),
                    sizeBytes: $file->size,
                    ownerUid: $ownerId,
                    ownerLabel: $ownerLabel,
                    folder: $folder,
                    storageUri: $file->uri,
                    uploadedAt: $now,
                    editorUid: $ownerId,
                    editorLabel: $ownerLabel,
                    updatedAt: $now,
                    revisionLog: 'Seeded',
                );
                $io->writeln(sprintf('  add    %s (%d KB)', $name, (int) round($file->size / 1024)));
                $added++;
            } catch (\Throwable $e) {
                $io->error(sprintf('  fail   %s: %s', $name, $e->getMessage()));
                $failed++;
            }
        }

        $io->writeln('');
        $io->writeln(sprintf(
            'Drive seed complete: %d added, %d skipped, %d failed. Owner: %s (uid %d), folder "%s".',
            $added,
            $skipped,
            $failed,
            $ownerLabel,
            $ownerId,
            $folder,
        ));

        return $failed > 0 && $added === 0 ? 1 : 0;
    }

    /**
     * @return list<string>
     */
    private function imageFiles(string $dir): array
    {
        $paths = [];
        foreach (scandir($dir) ?: [] as $entry) {
            $path = $dir . '/' . $entry;
            if (!is_file($path)) {
                continue;
            }
            if (in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true)) {
                $paths[] = $path;
            }
        }
        sort($paths);

        return $paths;
    }

    /**
     * Resolve the owner id + display label. An --owner-email that resolves to a
     * real account wins (full attribution); otherwise fall back to the supplied
     * --owner-id / --owner-label (defaulting to Matthew Owl, whose photos these
     * are).
     *
     * @return array{0:int,1:string}
     */
    private function resolveOwner(CliIO $io): array
    {
        $ownerId = (int) ($io->option('owner-id') ?? 1);
        $ownerLabel = trim((string) ($io->option('owner-label') ?? '')) ?: 'Matthew Owl';

        $email = strtolower(trim((string) ($io->option('owner-email') ?? '')));
        if ($email !== '' && $this->entityTypeManager !== null) {
            try {
                $user = $this->entityTypeManager->getStorage('user')->loadByKey('mail', $email);
                if ($user instanceof User) {
                    $name = $user->getName();

                    return [$user->id(), $name !== '' ? $name : $email];
                }
                $io->writeln(sprintf('  note   no account for %s; using fallback owner.', $email));
            } catch (\Throwable) {
                // fall through to the supplied id/label
            }
        }

        return [$ownerId, $ownerLabel];
    }
}
