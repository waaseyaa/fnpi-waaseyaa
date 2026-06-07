<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * Access posture for Document notes (document_note), the discussion thread.
 *
 * A note is part of working on a document, so it shares the Documents
 * permissions: any signed-in account may read; posting a note requires
 * `edit documents` (Editor and Admin); removing one requires
 * `administer documents` (Admin only; not exposed yet). Fails closed.
 *
 * @api
 */
#[PolicyAttribute(entityType: 'document_note')]
final class DocumentNoteAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'document_note';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($operation === 'view') {
            return $account->isAuthenticated()
                ? AccessResult::allowed('signed-in workspace users may read notes')
                : AccessResult::neutral('notes are workspace-only');
        }

        if ($operation === 'delete') {
            return $account->hasPermission(WorkspaceAccess::ADMINISTER_DOCUMENTS)
                ? AccessResult::allowed('administer documents may delete a note')
                : AccessResult::neutral('deleting a note requires administer documents');
        }

        return $account->hasPermission(WorkspaceAccess::EDIT_DOCUMENTS)
            ? AccessResult::allowed('edit documents may post')
            : AccessResult::neutral('posting a note requires edit documents');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if (!$this->appliesTo($entityTypeId)) {
            return AccessResult::neutral();
        }

        return $account->hasPermission(WorkspaceAccess::EDIT_DOCUMENTS)
            ? AccessResult::allowed('edit documents may post a note')
            : AccessResult::neutral('posting a note requires edit documents');
    }
}
