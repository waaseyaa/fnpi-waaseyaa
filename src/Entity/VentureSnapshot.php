<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\Attribute\Field;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\RevisionableInterface;
use Waaseyaa\Field\FieldStorage;

/**
 * Provenance for the Venture Numbers mirror: which modeling workbook state the
 * entered figures reflect, and as of when. The xlsx stays the modeling tool;
 * this row is what lets every screen say "mirrored from X, as of Y" instead of
 * showing undated numbers. One row in practice; re-mirroring updates it (a new
 * revision keeps the trail).
 *
 * Fields (all in the automatic _data blob, snapshotted per revision):
 *   as_of               date the mirror reflects (YYYY-MM-DD)
 *   model_version       the workbook the numbers come from
 *   note                free-text caveat shown with the banner
 *   editor_label        display name of who recorded the mirror (cache; the
 *                       acting uid is the framework's revision_author,
 *                       alpha.205+ — old revisions keep editor_uid in _data)
 *   updated_at          last-edited stamp
 */
#[ContentEntityType(id: 'venture_snapshot', label: 'Venture snapshot', description: 'Provenance for the venture-numbers mirror: workbook and as-of date.')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid', label: 'model_version', revision: 'revision_id')]
final class VentureSnapshot extends ContentEntityBase implements RevisionableInterface
{
    // ── Declared field definitions (alpha.204+ save-time validation) ──
    // Metadata declarations read by EntityType::fromClass() in
    // config/entity-types.php; values still flow through the value bag and
    // stay in the _data blob (stored: FieldStorage::Data).

    #[Field(required: true, label: 'As of', stored: FieldStorage::Data)]
    public string $as_of = '';

    #[Field(required: true, label: 'Model version', stored: FieldStorage::Data)]
    public string $model_version = '';

    #[Field(required: false, label: 'Note', stored: FieldStorage::Data)]
    public string $note = '';

    #[Field(required: false, stored: FieldStorage::Data)]
    public string $editor_label = '';

    #[Field(required: false, stored: FieldStorage::Data)]
    public string $updated_at = '';

    public function getAsOf(): string
    {
        return (string) ($this->get('as_of') ?? '');
    }

    public function setAsOf(string $asOf): static
    {
        $this->set('as_of', $asOf);

        return $this;
    }

    public function getModelVersion(): string
    {
        return (string) ($this->get('model_version') ?? '');
    }

    public function getNote(): string
    {
        return (string) ($this->get('note') ?? '');
    }

    public function getEditorLabel(): string
    {
        return (string) ($this->get('editor_label') ?? '');
    }

    /** Populate every defining field at once (the seed path). */
    public function fill(
        string $asOf,
        string $modelVersion,
        string $note,
        string $editorLabel,
        string $updatedAt,
    ): static {
        $this->set('as_of', $asOf);
        $this->set('model_version', $modelVersion);
        $this->set('note', $note);
        $this->set('editor_label', $editorLabel);
        $this->set('updated_at', $updatedAt);

        return $this;
    }

    /** Write a short summary into the revision log. */
    public function recordEdit(string $summary): static
    {
        $this->setRevisionLog($summary);

        return $this;
    }
}
