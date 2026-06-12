<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Field\FieldStorage;

/**
 * One revenue lane in the staff-only Venture Numbers section.
 *
 * Mirrors the external modeling workbook (the xlsx stays the modeling tool;
 * this is the status surface). Every figure is placeholder-grade until checked
 * against the workbook, and the UI labels it that way wherever it renders.
 *
 * The scenario grid is fifteen top-level integer fields (y1_worst .. y5_best),
 * whole Canadian dollars per year. Top-level fields, not a nested dict, so the
 * chat agent's entity.update proposals diff field by field on the approval
 * card instead of as one opaque blob.
 *
 * Fields (all in the automatic _data blob, snapshotted per revision):
 *   key                 stable string handle (technology, faraday, ...)
 *   title               lane title (the label key)
 *   summary             one-line honest framing of the lane
 *   y{1..5}_{worst|likely|best}  scenario values, whole CAD per year
 *   assumptions         list of assumption strings, shown beside the numbers
 *   notes               free-text working notes
 *   sort_order          ordering within the section
 *   editor_uid/_label   who made the current revision
 *   updated_at          last-edited stamp
 */
#[ContentEntityType(id: 'venture_lane', label: 'Venture lane', description: 'A revenue lane in the staff-only venture numbers section: scenario grid, assumptions, history.')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid', label: 'title', revision: 'revision_id')]
final class VentureLane extends ContentEntityBase
{
    /** Scenario keys, in display order. */
    public const SCENARIOS = ['worst', 'likely', 'best'];

    /** Modeled horizon in years. */
    public const YEARS = 5;

    // ── Declared field definitions (alpha.204+ save-time validation) ──
    //
    // These #[Field] properties are metadata declarations read by
    // EntityType::fromClass() in config/entity-types.php; values still flow
    // through the ContentEntityBase value bag (get()/set()), and every field
    // stays in the _data blob (stored: FieldStorage::Data — the sql-blob
    // backend materializes no column, and findBy() routes via json_extract).
    // The framework derives constraints from them and enforces on EVERY
    // save: required → NotBlank, settings.min → Range, PHP type → Type.
    // Strings that may legitimately be empty declare required: false
    // explicitly (the inferrer defaults required to "not nullable").

    #[Field(required: true, label: 'Lane key', stored: FieldStorage::Data)]
    public string $key = '';

    #[Field(required: true, label: 'Title', stored: FieldStorage::Data)]
    public string $title = '';

    #[Field(required: false, label: 'Summary', stored: FieldStorage::Data)]
    public string $summary = '';

    // The fifteen scenario grid cells: whole CAD per year, never negative.
    // Type('int') rejects floats and non-numeric values; Range(min: 0)
    // rejects negatives. (No field stores a scenario KEY — the scenario is
    // baked into the field names, so SCENARIOS needs no Choice constraint.)
    #[Field(required: false, settings: ['min' => 0], stored: FieldStorage::Data)]
    public int $y1_worst = 0;
    #[Field(required: false, settings: ['min' => 0], stored: FieldStorage::Data)]
    public int $y1_likely = 0;
    #[Field(required: false, settings: ['min' => 0], stored: FieldStorage::Data)]
    public int $y1_best = 0;
    #[Field(required: false, settings: ['min' => 0], stored: FieldStorage::Data)]
    public int $y2_worst = 0;
    #[Field(required: false, settings: ['min' => 0], stored: FieldStorage::Data)]
    public int $y2_likely = 0;
    #[Field(required: false, settings: ['min' => 0], stored: FieldStorage::Data)]
    public int $y2_best = 0;
    #[Field(required: false, settings: ['min' => 0], stored: FieldStorage::Data)]
    public int $y3_worst = 0;
    #[Field(required: false, settings: ['min' => 0], stored: FieldStorage::Data)]
    public int $y3_likely = 0;
    #[Field(required: false, settings: ['min' => 0], stored: FieldStorage::Data)]
    public int $y3_best = 0;
    #[Field(required: false, settings: ['min' => 0], stored: FieldStorage::Data)]
    public int $y4_worst = 0;
    #[Field(required: false, settings: ['min' => 0], stored: FieldStorage::Data)]
    public int $y4_likely = 0;
    #[Field(required: false, settings: ['min' => 0], stored: FieldStorage::Data)]
    public int $y4_best = 0;
    #[Field(required: false, settings: ['min' => 0], stored: FieldStorage::Data)]
    public int $y5_worst = 0;
    #[Field(required: false, settings: ['min' => 0], stored: FieldStorage::Data)]
    public int $y5_likely = 0;
    #[Field(required: false, settings: ['min' => 0], stored: FieldStorage::Data)]
    public int $y5_best = 0;

    #[Field(required: false, label: 'Assumptions', stored: FieldStorage::Data)]
    public array $assumptions = [];

    #[Field(required: false, label: 'Notes', stored: FieldStorage::Data)]
    public string $notes = '';

    #[Field(required: false, settings: ['min' => 0], stored: FieldStorage::Data)]
    public int $sort_order = 0;

    #[Field(required: false, stored: FieldStorage::Data)]
    public int $editor_uid = 0;

    #[Field(required: false, stored: FieldStorage::Data)]
    public string $editor_label = '';

    #[Field(required: false, stored: FieldStorage::Data)]
    public string $updated_at = '';

    /** The field name for one grid cell, e.g. y3_likely. */
    public static function scenarioField(int $year, string $scenario): string
    {
        return sprintf('y%d_%s', $year, $scenario);
    }

    /** @return list<string> all fifteen scenario field names */
    public static function scenarioFields(): array
    {
        $fields = [];
        for ($year = 1; $year <= self::YEARS; $year++) {
            foreach (self::SCENARIOS as $scenario) {
                $fields[] = self::scenarioField($year, $scenario);
            }
        }

        return $fields;
    }

    public function getKey(): string
    {
        return (string) ($this->get('key') ?? '');
    }

    public function getTitle(): string
    {
        return (string) ($this->get('title') ?? '');
    }

    public function setTitle(string $title): static
    {
        $this->set('title', $title);

        return $this;
    }

    public function getSummary(): string
    {
        return (string) ($this->get('summary') ?? '');
    }

    public function setSummary(string $summary): static
    {
        $this->set('summary', $summary);

        return $this;
    }

    /** One grid cell, whole CAD for that year and scenario. */
    public function getScenarioValue(int $year, string $scenario): int
    {
        return (int) ($this->get(self::scenarioField($year, $scenario)) ?? 0);
    }

    public function setScenarioValue(int $year, string $scenario, int $value): static
    {
        $this->set(self::scenarioField($year, $scenario), $value);

        return $this;
    }

    /**
     * The full grid: scenario => five yearly values (Yr 1 first).
     *
     * @return array<string, list<int>>
     */
    public function getScenarioGrid(): array
    {
        $grid = [];
        foreach (self::SCENARIOS as $scenario) {
            $row = [];
            for ($year = 1; $year <= self::YEARS; $year++) {
                $row[] = $this->getScenarioValue($year, $scenario);
            }
            $grid[$scenario] = $row;
        }

        return $grid;
    }

    /** @return list<string> */
    public function getAssumptions(): array
    {
        $assumptions = $this->get('assumptions');
        if (!is_array($assumptions)) {
            return [];
        }
        $out = [];
        foreach ($assumptions as $line) {
            if (is_string($line) && trim($line) !== '') {
                $out[] = trim($line);
            }
        }

        return $out;
    }

    /** @param list<string> $assumptions */
    public function setAssumptions(array $assumptions): static
    {
        $this->set('assumptions', array_values($assumptions));

        return $this;
    }

    public function getNotes(): string
    {
        return (string) ($this->get('notes') ?? '');
    }

    public function setNotes(string $notes): static
    {
        $this->set('notes', $notes);

        return $this;
    }

    public function getSortOrder(): int
    {
        return (int) ($this->get('sort_order') ?? 0);
    }

    public function getEditorUid(): int
    {
        return (int) ($this->get('editor_uid') ?? 0);
    }

    public function getEditorLabel(): string
    {
        return (string) ($this->get('editor_label') ?? '');
    }

    public function setEditor(int $uid, string $label): static
    {
        $this->set('editor_uid', $uid);
        $this->set('editor_label', $label);

        return $this;
    }

    public function getUpdatedAt(): string
    {
        return (string) ($this->get('updated_at') ?? '');
    }

    public function setUpdatedAt(string $updatedAt): static
    {
        $this->set('updated_at', $updatedAt);

        return $this;
    }

    /** When this revision was created, from the revision metadata. */
    public function getRevisionCreatedAt(): ?\DateTimeImmutable
    {
        return $this->revisionMetadata()?->revisionCreatedAt;
    }

    /**
     * Populate every defining field at once (the seed path). The caller saves;
     * with revisionDefault the save records the initial revision.
     *
     * @param array<string, list<int>> $grid scenario => five yearly values
     * @param list<string> $assumptions
     */
    public function fill(
        string $key,
        string $title,
        string $summary,
        array $grid,
        array $assumptions,
        string $notes,
        int $sortOrder,
        int $editorUid,
        string $editorLabel,
        string $updatedAt,
    ): static {
        $this->set('key', $key);
        $this->set('title', $title);
        $this->set('summary', $summary);
        foreach (self::SCENARIOS as $scenario) {
            for ($year = 1; $year <= self::YEARS; $year++) {
                $this->setScenarioValue($year, $scenario, (int) ($grid[$scenario][$year - 1] ?? 0));
            }
        }
        $this->setAssumptions($assumptions);
        $this->set('notes', $notes);
        $this->set('sort_order', $sortOrder);
        $this->set('editor_uid', $editorUid);
        $this->set('editor_label', $editorLabel);
        $this->set('updated_at', $updatedAt);

        return $this;
    }

    /** Write a short summary into the revision log (what this edit changed). */
    public function recordEdit(string $summary): static
    {
        $this->setRevisionLog($summary);

        return $this;
    }
}
