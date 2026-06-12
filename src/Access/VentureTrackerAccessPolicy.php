<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * Access posture for the venture tracker (venture_thread + venture_item) -- the
 * staff working board, distinct from the permission-gated Venture Numbers
 * section ({@see VentureAccessPolicy}).
 *
 * Any signed-in workspace account (Russell, Matthew) may read and write the
 * tracker; everything else, including anonymous, fails closed. No public
 * surface, no per-operation permission tier in this MVP (both accounts are
 * full editors). The board carries no personal third-party data, so it is the
 * MCP agent's to maintain -- McpAgentScope adds both types to its read and
 * write sets. View returns Neutral (not Forbidden) when unauthenticated: the
 * tracker has no public query-layer consumer to leak through, so the standard
 * neutral-denial posture is correct here.
 *
 * @api
 */
#[PolicyAttribute(entityType: ['venture_thread', 'venture_item'])]
final class VentureTrackerAccessPolicy implements AccessPolicyInterface
{
    private const TYPES = ['venture_thread', 'venture_item'];

    public function appliesTo(string $entityTypeId): bool
    {
        return in_array($entityTypeId, self::TYPES, true);
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        return $account->isAuthenticated()
            ? AccessResult::allowed('signed-in staff may work the venture tracker')
            : AccessResult::neutral('the venture tracker is staff-only');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if (!$this->appliesTo($entityTypeId)) {
            return AccessResult::neutral();
        }

        return $account->isAuthenticated()
            ? AccessResult::allowed('signed-in staff may add to the venture tracker')
            : AccessResult::neutral('the venture tracker is staff-only');
    }
}
