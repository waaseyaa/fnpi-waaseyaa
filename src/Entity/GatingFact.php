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
 * A named gating fact on a venture lane: something the model depends on that
 * is either still a placeholder or has been confirmed by a person.
 *
 * The status flip to "confirmed" is the load-bearing edit: it stamps who
 * confirmed and when (confirmed_by_uid / confirmed_by_label / confirmed_at),
 * and the revision history keeps the full placeholder-to-confirmed trail.
 * Flipping back to placeholder clears the confirmation stamp.
 *
 * Fields (all in the automatic _data blob, snapshotted per revision):
 *   key                  stable string handle (faraday-landed-cost, ...)
 *   lane_key             the parent lane's key (app-side soft reference)
 *   label                short fact name
 *   detail               what the fact is and why it gates the lane
 *   status               placeholder | confirmed
 *   confirmed_by_uid/_label/_at  who confirmed, set on the confirm flip
 *   sort_order           ordering within the lane
 *   editor_label         display name of who made the current revision (cache;
 *                        the acting uid is the framework's revision_author,
 *                        alpha.205+ — old revisions keep editor_uid in _data)
 *   updated_at           last-edited stamp
 */
#[ContentEntityType(id: 'gating_fact', label: 'Gating fact', description: 'A venture-lane gating fact with placeholder/confirmed status and confirmation attribution.')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid', label: 'label', revision: 'revision_id')]
final class GatingFact extends ContentEntityBase implements RevisionableInterface
{
    /** Valid statuses, in display order. */
    public const STATUSES = ['placeholder', 'confirmed'];

    // ── Declared field definitions (alpha.204+ save-time validation) ──
    //
    // Metadata declarations read by EntityType::fromClass() in
    // config/entity-types.php; values still flow through the value bag and
    // stay in the _data blob (stored: FieldStorage::Data). The load-bearing
    // one is `status`: settings.allowed_values derives a Choice constraint
    // from the canonical STATUSES set, so the framework rejects any other
    // value on every save. Strings that may legitimately be empty declare
    // required: false explicitly.

    #[Field(required: true, label: 'Fact key', stored: FieldStorage::Data)]
    public string $key = '';

    #[Field(required: true, label: 'Lane key', stored: FieldStorage::Data)]
    public string $lane_key = '';

    #[Field(required: true, label: 'Label', stored: FieldStorage::Data)]
    public string $label = '';

    #[Field(required: false, label: 'Detail', stored: FieldStorage::Data)]
    public string $detail = '';

    #[Field(required: true, settings: ['allowed_values' => self::STATUSES], label: 'Status', stored: FieldStorage::Data)]
    public string $status = 'placeholder';

    #[Field(required: false, stored: FieldStorage::Data)]
    public int $confirmed_by_uid = 0;

    #[Field(required: false, stored: FieldStorage::Data)]
    public string $confirmed_by_label = '';

    #[Field(required: false, stored: FieldStorage::Data)]
    public string $confirmed_at = '';

    #[Field(required: false, settings: ['min' => 0], stored: FieldStorage::Data)]
    public int $sort_order = 0;

    #[Field(required: false, stored: FieldStorage::Data)]
    public string $editor_label = '';

    #[Field(required: false, stored: FieldStorage::Data)]
    public string $updated_at = '';

    public function getKey(): string
    {
        return (string) ($this->get('key') ?? '');
    }

    public function getLaneKey(): string
    {
        return (string) ($this->get('lane_key') ?? '');
    }

    public function getLabel(): string
    {
        return (string) ($this->get('label') ?? '');
    }

    public function getDetail(): string
    {
        return (string) ($this->get('detail') ?? '');
    }

    public function setDetail(string $detail): static
    {
        $this->set('detail', $detail);

        return $this;
    }

    public function getStatus(): string
    {
        return (string) ($this->get('status') ?? 'placeholder');
    }

    public function setStatus(string $status): static
    {
        $this->set('status', $status);

        return $this;
    }

    public function isConfirmed(): bool
    {
        return $this->getStatus() === 'confirmed';
    }

    public function getConfirmedByUid(): int
    {
        return (int) ($this->get('confirmed_by_uid') ?? 0);
    }

    public function getConfirmedByLabel(): string
    {
        return (string) ($this->get('confirmed_by_label') ?? '');
    }

    public function getConfirmedAt(): string
    {
        return (string) ($this->get('confirmed_at') ?? '');
    }

    /** Stamp the confirmation (who and when). The caller flips status too. */
    public function setConfirmedBy(int $uid, string $label, string $at): static
    {
        $this->set('confirmed_by_uid', $uid);
        $this->set('confirmed_by_label', $label);
        $this->set('confirmed_at', $at);

        return $this;
    }

    /** Clear the confirmation stamp (the fact went back to placeholder). */
    public function clearConfirmation(): static
    {
        $this->set('confirmed_by_uid', 0);
        $this->set('confirmed_by_label', '');
        $this->set('confirmed_at', '');

        return $this;
    }

    public function getSortOrder(): int
    {
        return (int) ($this->get('sort_order') ?? 0);
    }

    /**
     * The acting account uid for this revision: the framework's
     * revision_author (recorded automatically since alpha.205, hydrated on
     * loadRevision()/listRevisions()), falling back to the editor_uid the app
     * snapshotted into _data before the framework owned authorship. 0 means
     * anonymous/system (the pre-upgrade seed convention).
     */
    public function getEditorUid(): int
    {
        $author = $this->revisionMetadata()?->revisionAuthor;
        if ($author !== null) {
            return $author;
        }

        return (int) ($this->get('editor_uid') ?? 0);
    }

    public function getEditorLabel(): string
    {
        return (string) ($this->get('editor_label') ?? '');
    }

    /**
     * Stamp the display name of the editor. The acting uid is NOT written
     * here any more: the framework records it as revision_author on save.
     */
    public function setEditorLabel(string $label): static
    {
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
     */
    public function fill(
        string $key,
        string $laneKey,
        string $label,
        string $detail,
        string $status,
        int $sortOrder,
        string $editorLabel,
        string $updatedAt,
    ): static {
        $this->set('key', $key);
        $this->set('lane_key', $laneKey);
        $this->set('label', $label);
        $this->set('detail', $detail);
        $this->set('status', $status);
        $this->set('confirmed_by_uid', 0);
        $this->set('confirmed_by_label', '');
        $this->set('confirmed_at', '');
        $this->set('sort_order', $sortOrder);
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
