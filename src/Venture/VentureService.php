<?php

declare(strict_types=1);

namespace App\Venture;

use App\Entity\GatingFact;
use App\Entity\VentureLane;
use App\Entity\VentureSnapshot;
use Symfony\Component\Uid\Uuid;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Entity\Validation\EntityValidationException;

/**
 * Orchestrates the staff-only Venture Numbers section over the framework
 * revision system. Lanes, gating facts, and the provenance snapshot are
 * revisionable entities; every numeric or status edit records a revision with
 * attribution (the rollback net). The roll-up is computed in PHP at read time
 * (the framework has no aggregation surface; six lanes is a loop).
 *
 * Attribution (alpha.205+): the framework records the acting account uid as
 * revision_author on every save automatically (request-scoped via
 * SessionMiddleware), so this service no longer writes editor_uid — only the
 * human-readable editor_label display cache stays app-side.
 *
 * All scenario values are whole Canadian dollars per year, placeholder-grade
 * by definition until checked against the modeling workbook.
 */
final class VentureService
{
    public function __construct(private readonly ?EntityTypeManager $entityTypeManager) {}

    /** @return list<VentureLane> all lanes, ordered by sort_order ascending */
    public function listLanes(): array
    {
        $lanes = [];
        foreach ($this->lanes()->findBy([]) as $entity) {
            if ($entity instanceof VentureLane) {
                $lanes[] = $entity;
            }
        }
        usort($lanes, static fn(VentureLane $a, VentureLane $b) => $a->getSortOrder() <=> $b->getSortOrder());

        return $lanes;
    }

    public function findLaneByKey(string $key): ?VentureLane
    {
        if ($key === '') {
            return null;
        }
        foreach ($this->lanes()->findBy(['key' => $key]) as $entity) {
            if ($entity instanceof VentureLane) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * Create a lane with its initial revision (the seed path).
     *
     * @param array<string, list<int>> $grid scenario => five yearly values
     * @param list<string> $assumptions
     */
    public function createLane(
        string $key,
        string $title,
        string $summary,
        array $grid,
        array $assumptions,
        string $notes,
        int $sortOrder,
        string $editorLabel,
        string $revisionLog,
    ): VentureLane {
        $lane = new VentureLane();
        $lane->set('uuid', Uuid::v4()->toRfc4122());
        $lane->fill($key, $title, $summary, $grid, $assumptions, $notes, $sortOrder, $editorLabel, gmdate('Y-m-d\TH:i:s\Z'));
        $lane->recordEdit($revisionLog);
        $lane->enforceIsNew();
        $this->lanes()->save($lane);

        return $lane;
    }

    /**
     * Apply an edit to a lane, recording a new revision with attribution.
     * $changes may carry scenario cell values (y1_worst .. y5_best, integers),
     * plus summary, notes, and assumptions (list of strings). Unknown keys are
     * ignored. Scenario validity (whole non-negative integers) is enforced by
     * the framework on save from the entity's declared field definitions
     * (Type('int') + Range(min: 0), alpha.204); an invalid value surfaces as
     * EntityValidationException, which this boundary maps to null — the same
     * invalid-input signal the controller has always turned into its 422 JSON
     * shape. Returns the stamp plus what changed, or null when the key is
     * unknown, a value is invalid, or nothing would change.
     *
     * @param array<string, mixed> $changes
     * @return array{editor_label:string, updated_at:string, changed:list<string>}|null
     */
    public function updateLane(string $key, array $changes, string $editorLabel): ?array
    {
        $lane = $this->findLaneByKey($key);
        if ($lane === null) {
            return null;
        }

        $changed = [];
        foreach (VentureLane::scenarioFields() as $field) {
            if (!array_key_exists($field, $changes)) {
                continue;
            }
            $raw = $changes[$field];
            // Whole-number coercion, deliberately kept app-side: the venture
            // entities declare no $casts, so the framework's cast-aware get()
            // does NOT coerce — a numeric string ("40000" from a text input)
            // or a whole JSON float (4.0) would hit the declared Type('int')
            // constraint raw and be rejected, although both were always
            // accepted here. 4.5 fails the whole-int equality, stays a float,
            // and is rejected by the framework at save (verified on alpha.207).
            if (is_numeric($raw) && (int) $raw == $raw) {
                $raw = (int) $raw;
            }
            if ($raw !== (int) ($lane->get($field) ?? 0)) {
                $lane->set($field, $raw);
                $changed[] = $field;
            }
        }
        if (array_key_exists('summary', $changes) && is_string($changes['summary']) && $changes['summary'] !== $lane->getSummary()) {
            $lane->setSummary($changes['summary']);
            $changed[] = 'summary';
        }
        if (array_key_exists('notes', $changes) && is_string($changes['notes']) && $changes['notes'] !== $lane->getNotes()) {
            $lane->setNotes($changes['notes']);
            $changed[] = 'notes';
        }
        if (array_key_exists('assumptions', $changes) && is_array($changes['assumptions'])) {
            $assumptions = [];
            foreach ($changes['assumptions'] as $line) {
                if (is_string($line) && trim($line) !== '') {
                    $assumptions[] = trim($line);
                }
            }
            if ($assumptions !== $lane->getAssumptions()) {
                $lane->setAssumptions($assumptions);
                $changed[] = 'assumptions';
            }
        }
        if ($changed === []) {
            return null;
        }

        $updatedAt = gmdate('Y-m-d\TH:i:s\Z');
        // The acting uid lands in revision_author on save (framework-owned,
        // alpha.205+); only the display label is app data now. NOTE: the lane
        // form does not send the revision id it was rendered from, so this
        // human path saves without SaveContext::withExpectedRevisionId() —
        // adopting conflict detection here needs a client change (send the
        // read revision_id, pass it through) and is a documented follow-up.
        $lane->setEditorLabel($editorLabel);
        $lane->setUpdatedAt($updatedAt);
        $lane->recordEdit($this->summarizeLaneEdit($changed));
        try {
            $this->lanes()->save($lane);
        } catch (EntityValidationException) {
            // Declared-constraint violation (negative, float, non-numeric):
            // nothing was persisted; null is the boundary's invalid-input
            // signal and the controller keeps its existing 422 JSON shape.
            return null;
        }

        return ['editor_label' => $editorLabel, 'updated_at' => $updatedAt, 'changed' => $changed];
    }

    /** @return list<VentureLane> revision history for a lane, newest first */
    public function listLaneHistory(string $key): array
    {
        $lane = $this->findLaneByKey($key);
        if ($lane === null) {
            return [];
        }
        $history = [];
        foreach ($this->lanes()->listRevisions((string) $lane->id()) as $rev) {
            if ($rev instanceof VentureLane) {
                $history[] = $rev;
            }
        }

        return $history;
    }

    /** @return list<GatingFact> all gating facts, ordered by sort_order */
    public function listFacts(): array
    {
        $facts = [];
        foreach ($this->facts()->findBy([]) as $entity) {
            if ($entity instanceof GatingFact) {
                $facts[] = $entity;
            }
        }
        usort($facts, static fn(GatingFact $a, GatingFact $b) => $a->getSortOrder() <=> $b->getSortOrder());

        return $facts;
    }

    /** @return array<string, list<GatingFact>> facts grouped by lane key */
    public function factsByLane(): array
    {
        $grouped = [];
        foreach ($this->listFacts() as $fact) {
            $grouped[$fact->getLaneKey()][] = $fact;
        }

        return $grouped;
    }

    public function findFactByKey(string $key): ?GatingFact
    {
        if ($key === '') {
            return null;
        }
        foreach ($this->facts()->findBy(['key' => $key]) as $entity) {
            if ($entity instanceof GatingFact) {
                return $entity;
            }
        }

        return null;
    }

    /** Create a gating fact with its initial revision (the seed path). */
    public function createFact(
        string $key,
        string $laneKey,
        string $label,
        string $detail,
        string $status,
        int $sortOrder,
        string $editorLabel,
        string $revisionLog,
    ): GatingFact {
        $fact = new GatingFact();
        $fact->set('uuid', Uuid::v4()->toRfc4122());
        $fact->fill($key, $laneKey, $label, $detail, $status, $sortOrder, $editorLabel, gmdate('Y-m-d\TH:i:s\Z'));
        $fact->recordEdit($revisionLog);
        $fact->enforceIsNew();
        $this->facts()->save($fact);

        return $fact;
    }

    /**
     * Apply a status flip and/or detail edit to a gating fact, recording a
     * revision. The flip to "confirmed" stamps who confirmed and when; the
     * flip back to "placeholder" clears the stamp. Returns the stamp plus what
     * changed, or null when the key is unknown, the status is invalid, or
     * nothing would change.
     *
     * $editorUid is still a parameter here (unlike updateLane) because the
     * confirmation stamp confirmed_by_uid is app-level domain data — who
     * CONFIRMED the fact, distinct from who edited the revision (which the
     * framework now records as revision_author).
     *
     * @return array{editor_label:string, updated_at:string, changed:list<string>, status:string, confirmed_by:string, confirmed_at:string}|null
     */
    public function updateFact(string $key, ?string $newStatus, ?string $detail, int $editorUid, string $editorLabel): ?array
    {
        $fact = $this->findFactByKey($key);
        if ($fact === null) {
            return null;
        }

        $changed = [];
        $updatedAt = gmdate('Y-m-d\TH:i:s\Z');
        if ($newStatus !== null && $newStatus !== $fact->getStatus()) {
            // Status validity is enforced by the framework on save: the
            // entity declares allowed_values = GatingFact::STATUSES, so an
            // unknown status throws EntityValidationException below (Choice
            // constraint, alpha.204) and this boundary returns null as before.
            $fact->setStatus($newStatus);
            if ($newStatus === 'confirmed') {
                $fact->setConfirmedBy($editorUid, $editorLabel, $updatedAt);
            } else {
                $fact->clearConfirmation();
            }
            $changed[] = 'status';
        }
        if ($detail !== null && $detail !== $fact->getDetail()) {
            $fact->setDetail($detail);
            $changed[] = 'detail';
        }
        if ($changed === []) {
            return null;
        }

        $fact->setEditorLabel($editorLabel);
        $fact->setUpdatedAt($updatedAt);
        $fact->recordEdit($this->summarizeFactEdit($changed, $fact, $editorLabel));
        try {
            $this->facts()->save($fact);
        } catch (EntityValidationException) {
            // Invalid status (Choice violation): nothing was persisted; null
            // keeps the controller's existing 422 JSON shape.
            return null;
        }

        return [
            'editor_label' => $editorLabel,
            'updated_at' => $updatedAt,
            'changed' => $changed,
            'status' => $fact->getStatus(),
            'confirmed_by' => $fact->getConfirmedByLabel(),
            'confirmed_at' => $fact->getConfirmedAt(),
        ];
    }

    /** @return list<GatingFact> revision history for a fact, newest first */
    public function listFactHistory(string $key): array
    {
        $fact = $this->findFactByKey($key);
        if ($fact === null) {
            return [];
        }
        $history = [];
        foreach ($this->facts()->listRevisions((string) $fact->id()) as $rev) {
            if ($rev instanceof GatingFact) {
                $history[] = $rev;
            }
        }

        return $history;
    }

    /** The current provenance snapshot (latest row), or null before seeding. */
    public function snapshot(): ?VentureSnapshot
    {
        $latest = null;
        foreach ($this->snapshots()->findBy([]) as $entity) {
            if ($entity instanceof VentureSnapshot && ($latest === null || (int) $entity->id() > (int) $latest->id())) {
                $latest = $entity;
            }
        }

        return $latest;
    }

    /** Create the provenance snapshot (the seed path). */
    public function createSnapshot(string $asOf, string $modelVersion, string $note, string $editorLabel): VentureSnapshot
    {
        $snapshot = new VentureSnapshot();
        $snapshot->set('uuid', Uuid::v4()->toRfc4122());
        $snapshot->fill($asOf, $modelVersion, $note, $editorLabel, gmdate('Y-m-d\TH:i:s\Z'));
        $snapshot->recordEdit('Initial venture-numbers mirror recorded');
        $snapshot->enforceIsNew();
        $this->snapshots()->save($snapshot);

        return $snapshot;
    }

    /**
     * Sum the scenario grids across lanes: scenario => five yearly totals.
     * Pure and static so the roll-up math is unit-testable without storage.
     *
     * @param list<VentureLane> $lanes
     * @return array<string, list<int>>
     */
    public static function rollup(array $lanes): array
    {
        $totals = [];
        foreach (VentureLane::SCENARIOS as $scenario) {
            $totals[$scenario] = array_fill(0, VentureLane::YEARS, 0);
        }
        foreach ($lanes as $lane) {
            foreach (VentureLane::SCENARIOS as $scenario) {
                for ($year = 1; $year <= VentureLane::YEARS; $year++) {
                    $totals[$scenario][$year - 1] += $lane->getScenarioValue($year, $scenario);
                }
            }
        }

        return $totals;
    }

    /**
     * One lane's share of a roll-up year, as a rounded percentage (0 when the
     * total is zero). Used for the "Technology share of Yr 5" line.
     *
     * @param array<string, list<int>> $totals
     */
    public static function shareOfYear(array $totals, VentureLane $lane, string $scenario, int $year): int
    {
        $total = $totals[$scenario][$year - 1] ?? 0;
        if ($total <= 0) {
            return 0;
        }

        return (int) round($lane->getScenarioValue($year, $scenario) / $total * 100);
    }

    /**
     * @param list<string> $changed
     */
    private function summarizeLaneEdit(array $changed): string
    {
        $cells = array_values(array_intersect($changed, VentureLane::scenarioFields()));
        $parts = [];
        if ($cells !== []) {
            $parts[] = count($cells) === 1
                ? 'Scenario value ' . $cells[0] . ' updated'
                : count($cells) . ' scenario values updated';
        }
        foreach (['summary', 'notes', 'assumptions'] as $field) {
            if (in_array($field, $changed, true)) {
                $parts[] = ucfirst($field) . ' updated';
            }
        }

        return $parts === [] ? 'Lane updated' : implode(', ', $parts);
    }

    /**
     * @param list<string> $changed
     */
    private function summarizeFactEdit(array $changed, GatingFact $fact, string $editorLabel): string
    {
        if (in_array('status', $changed, true)) {
            $summary = $fact->isConfirmed()
                ? 'Confirmed by ' . $editorLabel
                : 'Set back to placeholder';

            return in_array('detail', $changed, true) ? $summary . ', detail updated' : $summary;
        }

        return 'Detail updated';
    }

    private function lanes(): EntityRepositoryInterface
    {
        return $this->repository('venture_lane');
    }

    private function facts(): EntityRepositoryInterface
    {
        return $this->repository('gating_fact');
    }

    private function snapshots(): EntityRepositoryInterface
    {
        return $this->repository('venture_snapshot');
    }

    private function repository(string $type): EntityRepositoryInterface
    {
        if ($this->entityTypeManager === null) {
            throw new \LogicException('VentureService requires a booted kernel (EntityTypeManager).');
        }

        return $this->entityTypeManager->getRepository($type);
    }
}
