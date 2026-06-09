<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * Access posture for public marketing pages (`page`), the Anokii Pages tool.
 *
 * The workspace is gated to signed-in accounts, so any authenticated user may
 * read. Editing a page — saving a draft revision (update / create) — requires
 * the `edit pages` permission. Moving the live view (publish / rollback) is a
 * separate, higher bar: `publish pages`. Deleting requires `administer pages`
 * (not exposed yet). Everything else fails closed via Neutral.
 *
 * @api
 */
#[PolicyAttribute(entityType: 'page')]
final class PageAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'page';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($operation === 'view') {
            return $account->isAuthenticated()
                ? AccessResult::allowed('signed-in workspace users may read pages')
                : AccessResult::neutral('pages editing is workspace-only');
        }

        // publish / rollback: move the live (published) pointer.
        if ($operation === 'publish') {
            return $account->hasPermission(WorkspaceAccess::PUBLISH_PAGES)
                ? AccessResult::allowed('publish pages may change the live view')
                : AccessResult::neutral('publishing a page requires publish pages');
        }

        if ($operation === 'delete') {
            return $account->hasPermission(WorkspaceAccess::ADMINISTER_PAGES)
                ? AccessResult::allowed('administer pages may delete')
                : AccessResult::neutral('deleting a page requires administer pages');
        }

        // update (save a draft) and any other write-like operation.
        return $account->hasPermission(WorkspaceAccess::EDIT_PAGES)
            ? AccessResult::allowed('edit pages may save a draft')
            : AccessResult::neutral('editing a page requires edit pages');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if (!$this->appliesTo($entityTypeId)) {
            return AccessResult::neutral();
        }

        return $account->hasPermission(WorkspaceAccess::EDIT_PAGES)
            ? AccessResult::allowed('edit pages may create')
            : AccessResult::neutral('creating a page requires edit pages');
    }
}
