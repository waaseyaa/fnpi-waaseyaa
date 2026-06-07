<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * Access posture for Identity Workspace pillars (identity_pillar).
 *
 * The workspace is gated to signed-in accounts, so any authenticated user may
 * read. Writing a pillar (update status/notes/fields, create) requires the
 * `edit identity` permission (Editor and Admin). Deleting requires
 * `administer identity` (Admin only; deletes are not exposed yet). Everything
 * else fails closed via Neutral, which the handler treats as denied.
 *
 * @api
 */
#[PolicyAttribute(entityType: 'identity_pillar')]
final class IdentityPillarAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'identity_pillar';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($operation === 'view') {
            return $account->isAuthenticated()
                ? AccessResult::allowed('signed-in workspace users may read identity')
                : AccessResult::neutral('identity is workspace-only');
        }

        if ($operation === 'delete') {
            return $account->hasPermission(WorkspaceAccess::ADMINISTER_IDENTITY)
                ? AccessResult::allowed('administer identity may delete')
                : AccessResult::neutral('deleting a pillar requires administer identity');
        }

        // update (and any other write-like operation).
        return $account->hasPermission(WorkspaceAccess::EDIT_IDENTITY)
            ? AccessResult::allowed('edit identity may update')
            : AccessResult::neutral('editing a pillar requires edit identity');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if (!$this->appliesTo($entityTypeId)) {
            return AccessResult::neutral();
        }

        return $account->hasPermission(WorkspaceAccess::EDIT_IDENTITY)
            ? AccessResult::allowed('edit identity may create')
            : AccessResult::neutral('creating a pillar requires edit identity');
    }
}
