<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\ContentEntityBase;

/**
 * One thread (card) of the venture tracker: a workstream with an ordered list
 * of {@see VentureItem} entries.
 *
 * Not revisionable: the tracker is a live working board the workspace (and the
 * MCP agent that maintains it) edits in place; there is no draft/publish step.
 * Staff-only by policy; never public. Items reference their thread by uuid.
 *
 * Fields (in the automatic _data blob):
 *   title       the card heading (e.g. "fnprocure.ca copy refresh")
 *   sort_order  position among threads
 *   next        the per-thread "Next" footer line (optional)
 */
#[ContentEntityType(id: 'venture_thread', label: 'Venture thread', description: 'A workstream card in the venture tracker.')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid', label: 'title')]
final class VentureThread extends ContentEntityBase
{
    public function getTitle(): string
    {
        return (string) ($this->get('title') ?? '');
    }

    public function getSortOrder(): int
    {
        return (int) ($this->get('sort_order') ?? 0);
    }

    public function getNext(): string
    {
        return (string) ($this->get('next') ?? '');
    }

    public function fill(string $title, int $sortOrder, string $next): static
    {
        $this->set('title', $title);
        $this->set('sort_order', $sortOrder);
        $this->set('next', $next);

        return $this;
    }
}
