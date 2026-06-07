<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * Access posture for Documents (document).
 *
 * Any signed-in account may read. Writing a document (create, add a version,
 * set-current, rollback) requires the `edit documents` permission (Editor and
 * Admin). Deleting requires `administer documents` (Admin only; deletes are not
 * exposed yet). Fails closed via Neutral.
 *
 * @api
 */
#[PolicyAttribute(entityType: 'document')]
final class DocumentAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'document';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($operation === 'view') {
            return $account->isAuthenticated()
                ? AccessResult::allowed('signed-in workspace users may read documents')
                : AccessResult::neutral('documents are workspace-only');
        }

        if ($operation === 'delete') {
            return $account->hasPermission(WorkspaceAccess::ADMINISTER_DOCUMENTS)
                ? AccessResult::allowed('administer documents may delete')
                : AccessResult::neutral('deleting a document requires administer documents');
        }

        return $account->hasPermission(WorkspaceAccess::EDIT_DOCUMENTS)
            ? AccessResult::allowed('edit documents may update')
            : AccessResult::neutral('editing a document requires edit documents');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if (!$this->appliesTo($entityTypeId)) {
            return AccessResult::neutral();
        }

        return $account->hasPermission(WorkspaceAccess::EDIT_DOCUMENTS)
            ? AccessResult::allowed('edit documents may create')
            : AccessResult::neutral('creating a document requires edit documents');
    }
}
