<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\Attribute\ContentEntityKeys;
use Waaseyaa\Entity\Attribute\ContentEntityType;
use Waaseyaa\Entity\ContentEntityBase;

/**
 * One line of a {@see VentureThread}: a tracker entry with a status dot and an
 * optional muted who-note.
 *
 * Not revisionable (same reasoning as VentureThread): a live board edited in
 * place by staff and the MCP agent. Staff-only by policy; never public.
 *
 * Fields (in the automatic _data blob):
 *   thread_uuid  the VentureThread this belongs to
 *   body         the line text
 *   status       done | doing | wait | todo | dec (the dot colour)
 *   who          optional muted attribution / detail note
 *   sort_order   position within the thread
 *   created_at / updated_at  ISO-8601 stamps
 */
#[ContentEntityType(id: 'venture_item', label: 'Venture item', description: 'A status line in a venture tracker thread.')]
#[ContentEntityKeys(id: 'id', uuid: 'uuid', label: 'body')]
final class VentureItem extends ContentEntityBase
{
    /** Valid statuses, in the tracker's legend order. */
    public const array STATUSES = ['done', 'doing', 'wait', 'todo', 'dec'];

    public function getThreadUuid(): string
    {
        return (string) ($this->get('thread_uuid') ?? '');
    }

    public function getBody(): string
    {
        return (string) ($this->get('body') ?? '');
    }

    public function getStatus(): string
    {
        $s = (string) ($this->get('status') ?? 'todo');

        return in_array($s, self::STATUSES, true) ? $s : 'todo';
    }

    public function getWho(): string
    {
        return (string) ($this->get('who') ?? '');
    }

    public function getSortOrder(): int
    {
        return (int) ($this->get('sort_order') ?? 0);
    }

    public function getCreatedAt(): string
    {
        return (string) ($this->get('created_at') ?? '');
    }

    public function getUpdatedAt(): string
    {
        return (string) ($this->get('updated_at') ?? '');
    }

    public function fill(string $threadUuid, string $body, string $status, string $who, int $sortOrder, string $now): static
    {
        $this->set('thread_uuid', $threadUuid);
        $this->set('body', $body);
        $this->set('status', in_array($status, self::STATUSES, true) ? $status : 'todo');
        $this->set('who', $who);
        $this->set('sort_order', $sortOrder);
        $this->set('created_at', $now);
        $this->set('updated_at', $now);

        return $this;
    }
}
