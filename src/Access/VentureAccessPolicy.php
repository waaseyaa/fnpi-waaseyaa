<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * Access posture for the staff-only Venture Numbers section (venture_lane,
 * gating_fact, venture_snapshot).
 *
 * This is the first permission-gated READ in the workspace, and deliberately
 * so: the section carries revenue modeling (defence figures included) that
 * must be invisible to the public AND to authenticated accounts without the
 * `view ventures` permission. Unlike every other workspace policy, view
 * returns FORBIDDEN (not Neutral) when the permission is missing: Forbidden is
 * the only result the entity query layer drops rows on, so a neutral denial
 * would protect the HTTP checkpoints but leak through query-layer consumers.
 * Forbidden short-circuits safely here because this policy is the only one
 * that applies to the venture types.
 *
 * Writes follow the house pattern: `edit ventures` for lane/fact edits and
 * creates, `confirm ventures` for the gating-fact status flip (the operation
 * the controller checks as 'confirm'), `administer ventures` for delete
 * (which no UI exposes; the section is no-delete by posture).
 *
 * @api
 */
#[PolicyAttribute(entityType: ['venture_lane', 'gating_fact', 'venture_snapshot'])]
final class VentureAccessPolicy implements AccessPolicyInterface
{
    private const TYPES = ['venture_lane', 'gating_fact', 'venture_snapshot'];

    public function appliesTo(string $entityTypeId): bool
    {
        return in_array($entityTypeId, self::TYPES, true);
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($operation === 'view') {
            return $account->hasPermission(WorkspaceAccess::VIEW_VENTURES)
                ? AccessResult::allowed('view ventures may read the venture numbers')
                : AccessResult::forbidden('venture numbers are staff-only (view ventures required)');
        }

        if ($operation === 'delete') {
            return $account->hasPermission(WorkspaceAccess::ADMINISTER_VENTURES)
                ? AccessResult::allowed('administer ventures may delete')
                : AccessResult::neutral('deleting venture content requires administer ventures');
        }

        if ($operation === 'confirm') {
            return $account->hasPermission(WorkspaceAccess::CONFIRM_VENTURES)
                ? AccessResult::allowed('confirm ventures may flip a gating fact')
                : AccessResult::neutral('confirming a gating fact requires confirm ventures');
        }

        // update (and any other write-like operation).
        return $account->hasPermission(WorkspaceAccess::EDIT_VENTURES)
            ? AccessResult::allowed('edit ventures may update')
            : AccessResult::neutral('editing venture content requires edit ventures');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if (!$this->appliesTo($entityTypeId)) {
            return AccessResult::neutral();
        }

        return $account->hasPermission(WorkspaceAccess::EDIT_VENTURES)
            ? AccessResult::allowed('edit ventures may create')
            : AccessResult::neutral('creating venture content requires edit ventures');
    }
}
