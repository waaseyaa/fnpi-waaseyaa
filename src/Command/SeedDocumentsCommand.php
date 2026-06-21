<?php

declare(strict_types=1);

namespace App\Command;

use App\Documents\DocumentService;
use Waaseyaa\CLI\Command\SymfonyCommandIO;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\User\User;

/**
 * `vendor/bin/waaseyaa app:seed-documents`
 *
 * Seeds the CANCOM funding strategy as the first Documents entry, with its real
 * lineage: three versions in order (v0 Matthew original, v1 FNPI branded draft
 * by Russell, v2 Matthew CANCOM-VigilAInt, which is current), folder "CANCOM",
 * plus Russell's opening note. Each version is a Waaseyaa revision; the source
 * .docx and pre-converted .pdf for each ship in resources/seed/cancom, so the
 * seed does not depend on Gotenberg's first run.
 *
 * Idempotent: if the CANCOM document already exists it is left untouched.
 */
final class SeedDocumentsCommand
{
    private const string TITLE = 'CANCOM Funding and Growth Strategy';
    private const string FOLDER = 'CANCOM';

    /** @var list<array{file:string,label:string,who:string}> */
    private const array VERSIONS = [
        ['file' => 'v0-matthew-original', 'label' => 'Matthew original (v0)', 'who' => 'matthew'],
        ['file' => 'v1-fnpi-branded-draft', 'label' => 'FNPI branded draft (v1)', 'who' => 'russell'],
        ['file' => 'v2-matthew-cancom-vigilaint', 'label' => 'Matthew CANCOM-VigilAInt (v2)', 'who' => 'matthew'],
    ];

    private const string OPENING_NOTE =
        "Open question on v2 (CANCOM-VigilAInt): the positioning section now reads as two Indigenous "
        . "leads. It calls CANCOM-VigilAInt an Indigenous-led JV and CANCOM Indigenous security "
        . "expertise, which contradicts FNPI being the single Indigenous prime and risks the fronting "
        . "problem with NACCA. Confirm the real structure: FNPI is the 51 percent plus Indigenous owner "
        . "and prime, with CANCOM (security) and VigilAInt (AI) as capability partners under FNPI "
        . "control, and clarify whether either is actually Indigenous-owned. Once confirmed, v3 restores "
        . "FNPI as the sole Indigenous lead.";

    public function __construct(
        private readonly DocumentService $documents,
        private readonly ?EntityTypeManagerInterface $entityTypeManager,
        private readonly string $seedDir,
    ) {}

    public function run(SymfonyCommandIO $io): int
    {
        if (!is_dir($this->seedDir)) {
            $io->error('Seed directory not found: ' . $this->seedDir);

            return 1;
        }

        // Idempotent: skip if the CANCOM document already exists.
        foreach ($this->documents->listDocuments() as $existing) {
            if ($existing->getTitle() === self::TITLE && $existing->getFolder() === self::FOLDER) {
                $io->writeln(sprintf('  skip   "%s" already exists. Nothing to do.', self::TITLE));

                return 0;
            }
        }

        [$matthewUid, $matthewLabel] = $this->resolveUser($io, (string) ($io->option('matthew-email') ?? 'matthew@fnprocure.ca'), 'Matthew Owl');
        [$russellUid, $russellLabel] = $this->resolveUser($io, (string) ($io->option('russell-email') ?? 'russell@fnprocure.ca'), 'Russell Jones');

        $who = [
            'matthew' => [$matthewUid, $matthewLabel],
            'russell' => [$russellUid, $russellLabel],
        ];

        $uuid = null;
        foreach (self::VERSIONS as $i => $version) {
            $docx = $this->seedDir . '/' . $version['file'] . '.docx';
            $pdf = $this->seedDir . '/' . $version['file'] . '.pdf';
            if (!is_file($docx)) {
                $io->error('Missing seed source: ' . $docx);

                return 1;
            }
            $preview = is_file($pdf) ? $pdf : null;
            [$uid, $label] = $who[$version['who']];

            try {
                if ($i === 0) {
                    $doc = $this->documents->createDocument(
                        title: self::TITLE,
                        folder: self::FOLDER,
                        ownerUid: $uid,
                        ownerLabel: $label,
                        sourcePath: $docx,
                        sourceFilename: basename($docx),
                        versionLabel: $version['label'],
                        previewPath: $preview,
                    );
                    $uuid = (string) ($doc->get('uuid') ?? '');
                } else {
                    $this->documents->addVersion(
                        uuid: (string) $uuid,
                        sourcePath: $docx,
                        sourceFilename: basename($docx),
                        versionLabel: $version['label'],
                        authorUid: $uid,
                        authorLabel: $label,
                        previewPath: $preview,
                    );
                }
                $io->writeln(sprintf('  add    %s by %s%s', $version['label'], $label, $preview !== null ? ' (with preview)' : ''));
            } catch (\Throwable $e) {
                $io->error(sprintf('  fail   %s: %s', $version['label'], $e->getMessage()));

                return 1;
            }
        }

        if ($uuid === null || $uuid === '') {
            $io->error('Could not determine the seeded document id.');

            return 1;
        }

        // Russell's opening note.
        $this->documents->addNote($uuid, $russellUid, $russellLabel, self::OPENING_NOTE);
        $io->writeln(sprintf('  note   opening note by %s', $russellLabel));

        $io->writeln('');
        $io->writeln(sprintf(
            'Documents seed complete: "%s" (folder "%s") with %d versions, v2 current, 1 note. uuid %s',
            self::TITLE,
            self::FOLDER,
            count(self::VERSIONS),
            $uuid,
        ));

        return 0;
    }

    /**
     * @return array{0:int,1:string}
     */
    private function resolveUser(SymfonyCommandIO $io, string $email, string $fallbackLabel): array
    {
        $email = strtolower(trim($email));
        if ($email !== '' && $this->entityTypeManager !== null) {
            try {
                $user = $this->entityTypeManager->getStorage('user')->loadByKey('mail', $email);
                if ($user instanceof User) {
                    $name = $user->getName();

                    return [$user->id(), $name !== '' ? $name : $email];
                }
                $io->writeln(sprintf('  note   no account for %s; attributing to "%s".', $email, $fallbackLabel));
            } catch (\Throwable) {
                // fall through to the fallback label
            }
        }

        return [1, $fallbackLabel];
    }
}
