<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * Access posture for public contact-form submissions (contact_submission).
 *
 * Submissions carry personal contact data, so the posture is the tightest in
 * the workspace: any signed-in workspace account may read the inbox; marking
 * read (update) and deleting require `manage inbox` (Editor and Admin).
 * CREATE IS NEVER GRANTED through the entity gate: the public form writes
 * through ContactSubmitController server-side, and no workspace surface,
 * agent, or API may mint submissions. The MCP agent scope additionally
 * excludes this type from its read/write allowlists (see McpAgentScope).
 * Everything else fails closed via Neutral.
 *
 * @api
 */
#[PolicyAttribute(entityType: 'contact_submission')]
final class ContactSubmissionAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'contact_submission';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if ($operation === 'view') {
            return $account->isAuthenticated()
                ? AccessResult::allowed('signed-in workspace users may read the inbox')
                : AccessResult::neutral('the inbox is workspace-only');
        }

        if ($operation === 'update' || $operation === 'delete') {
            return $account->hasPermission(WorkspaceAccess::MANAGE_INBOX)
                ? AccessResult::allowed('manage inbox may ' . $operation)
                : AccessResult::neutral($operation . ' requires manage inbox');
        }

        return AccessResult::neutral('no other operations exist on submissions');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if (!$this->appliesTo($entityTypeId)) {
            return AccessResult::neutral();
        }

        // Deliberately never allowed: submissions are created only by the
        // public contact endpoint, server-side, outside the entity gate.
        return AccessResult::neutral('submissions are created by the public contact form only');
    }
}
