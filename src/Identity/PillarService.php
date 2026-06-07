<?php

declare(strict_types=1);

namespace App\Identity;

use App\Entity\Pillar;
use Symfony\Component\Uid\Uuid;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

/**
 * Orchestrates the entity-native Identity Workspace over the framework revision
 * system. A pillar is a revisionable `identity_pillar` entity; each status or
 * notes edit records a new revision (listRevisions() is the history).
 *
 * This replaces the raw-table PillarRepository: the same read/edit surface, now
 * on registered entities with full per-pillar history and attribution.
 */
final class PillarService
{
    /** Editable maturity statuses. */
    public const STATUSES = Pillar::STATUSES;

    public function __construct(private readonly ?EntityTypeManager $entityTypeManager) {}

    /** @return list<Pillar> all pillars, ordered by sort_order ascending */
    public function listPillars(): array
    {
        $pillars = [];
        foreach ($this->pillars()->findBy([]) as $entity) {
            if ($entity instanceof Pillar) {
                $pillars[] = $entity;
            }
        }
        usort($pillars, static fn(Pillar $a, Pillar $b) => $a->getSortOrder() <=> $b->getSortOrder());

        return $pillars;
    }

    public function count(): int
    {
        return count($this->listPillars());
    }

    public function findByPid(string $pid): ?Pillar
    {
        if ($pid === '') {
            return null;
        }
        foreach ($this->pillars()->findBy(['pid' => $pid]) as $entity) {
            if ($entity instanceof Pillar) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * Create a pillar with its initial revision. Used by migration (verbatim
     * import) and the fresh-install seed. The caller supplies the attribution
     * stamp (editorLabel / updatedAt) so a migrated pillar carries the original
     * editor and time, not the import time.
     *
     * @param list<array{t:string,cyan:bool}> $pills
     */
    public function createPillar(
        string $pid,
        string $section,
        string $title,
        string $nowLabel,
        string $body,
        bool $isQuote,
        string $decideLabel,
        string $decision,
        string $status,
        string $notes,
        array $pills,
        bool $isFull,
        int $sortOrder,
        int $editorUid,
        string $editorLabel,
        string $updatedAt,
        string $revisionLog,
    ): Pillar {
        $pillar = new Pillar();
        $pillar->set('uuid', Uuid::v4()->toRfc4122());
        $pillar->fill(
            $pid,
            $section,
            $title,
            $nowLabel,
            $body,
            $isQuote,
            $decideLabel,
            $decision,
            $status,
            $notes,
            $pills,
            $isFull,
            $sortOrder,
            $editorUid,
            $editorLabel,
            $updatedAt,
        );
        $pillar->recordEdit($revisionLog);
        $pillar->enforceIsNew();
        $this->pillars()->save($pillar);

        return $pillar;
    }

    /**
     * Apply a status and/or notes edit, recording a new revision with
     * attribution. Returns the stamp (editor + time) plus what changed, or null
     * when the pid is unknown, the status is invalid, or nothing would change.
     *
     * @return array{editor_label:string, updated_at:string, changed:list<string>}|null
     */
    public function update(string $pid, ?string $status, ?string $notes, int $editorUid, string $editorLabel): ?array
    {
        $pillar = $this->findByPid($pid);
        if ($pillar === null) {
            return null;
        }

        $changed = [];
        if ($status !== null && $status !== $pillar->getStatus()) {
            if (!in_array($status, Pillar::STATUSES, true)) {
                return null;
            }
            $pillar->setStatus($status);
            $changed[] = 'status';
        }
        if ($notes !== null && $notes !== $pillar->getNotes()) {
            $pillar->setNotes($notes);
            $changed[] = 'notes';
        }
        if ($changed === []) {
            return null;
        }

        $updatedAt = gmdate('Y-m-d\TH:i:s\Z');
        $pillar->setEditor($editorUid, $editorLabel);
        $pillar->setUpdatedAt($updatedAt);
        $pillar->recordEdit($this->summarize($changed, $pillar));
        $this->pillars()->save($pillar);

        return ['editor_label' => $editorLabel, 'updated_at' => $updatedAt, 'changed' => $changed];
    }

    /** @return list<Pillar> revision history for a pillar, newest first */
    public function listHistory(string $pid): array
    {
        $pillar = $this->findByPid($pid);
        if ($pillar === null) {
            return [];
        }
        $history = [];
        foreach ($this->pillars()->listRevisions((string) $pillar->id()) as $rev) {
            if ($rev instanceof Pillar) {
                $history[] = $rev;
            }
        }

        return $history;
    }

    /**
     * Counts per status across all pillars (for the maturity bar).
     *
     * @return array{defined:int, draft:int, work:int, gap:int, total:int}
     */
    public function statusCounts(): array
    {
        $counts = ['defined' => 0, 'draft' => 0, 'work' => 0, 'gap' => 0];
        foreach ($this->listPillars() as $pillar) {
            $s = $pillar->getStatus();
            if (isset($counts[$s])) {
                $counts[$s]++;
            }
        }
        $counts['total'] = $counts['defined'] + $counts['draft'] + $counts['work'] + $counts['gap'];

        return $counts;
    }

    /**
     * @param list<string> $changed
     */
    private function summarize(array $changed, Pillar $pillar): string
    {
        if ($changed === ['status']) {
            return 'Status set to ' . $pillar->getStatus();
        }
        if ($changed === ['notes']) {
            return 'Notes updated';
        }

        return 'Status set to ' . $pillar->getStatus() . ', notes updated';
    }

    private function pillars(): EntityRepositoryInterface
    {
        if ($this->entityTypeManager === null) {
            throw new \LogicException('PillarService requires a booted kernel (EntityTypeManager).');
        }

        return $this->entityTypeManager->getRepository('identity_pillar');
    }
}
