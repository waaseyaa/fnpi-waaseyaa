<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * Access posture for Drive files (drive_asset).
 *
 * Drive is a Nation-shared bucket: any signed-in account may read every file.
 * Uploading or editing a file requires the `edit drive` permission (Editor and
 * Admin). Deleting requires `administer drive` (Admin only): a Drive delete
 * removes the bytes and is not recoverable, so it is the destructive tier.
 * Fails closed via Neutral, mirroring the Identity and Documents policies.
 *
 * @api
 */
#[PolicyAttribute(entityType: 'drive_asset')]
final class DriveFileAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'drive_asset';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($operation === 'view') {
            return $account->isAuthenticated()
                ? AccessResult::allowed('signed-in workspace users may read Drive')
                : AccessResult::neutral('Drive is workspace-only');
        }

        if ($operation === 'delete') {
            return $account->hasPermission(WorkspaceAccess::ADMINISTER_DRIVE)
                ? AccessResult::allowed('administer drive may delete')
                : AccessResult::neutral('deleting a Drive file requires administer drive');
        }

        return $account->hasPermission(WorkspaceAccess::EDIT_DRIVE)
            ? AccessResult::allowed('edit drive may update')
            : AccessResult::neutral('editing a Drive file requires edit drive');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if (!$this->appliesTo($entityTypeId)) {
            return AccessResult::neutral();
        }

        return $account->hasPermission(WorkspaceAccess::EDIT_DRIVE)
            ? AccessResult::allowed('edit drive may upload')
            : AccessResult::neutral('uploading a Drive file requires edit drive');
    }
}
