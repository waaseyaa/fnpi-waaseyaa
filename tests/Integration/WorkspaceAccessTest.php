<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Access\WorkspaceAccess;
use App\Entity\Document;
use App\Entity\DocumentNote;
use App\Entity\DriveFile;
use App\Entity\Pillar;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\User\User;

/**
 * Increment 1: role-based access control for the Anokii workspace, mapped onto
 * Waaseyaa's role/permission substrate. Covers the role model, role application
 * to an account, and the three entity policies (via the same handler the UI
 * controllers use). The live Pi check confirms no lockout for the real accounts.
 */
final class WorkspaceAccessTest extends TestCase
{
    /** A controllable account for policy assertions. */
    private function account(bool $authenticated, array $permissions, array $roles = []): AccountInterface
    {
        return new class($authenticated, $permissions, $roles) implements AccountInterface {
            /** @param list<string> $permissions @param list<string> $roles */
            public function __construct(
                private readonly bool $authenticated,
                private readonly array $permissions,
                private readonly array $roles,
            ) {}

            public function id(): int|string
            {
                return 1;
            }

            public function hasPermission(string $permission): bool
            {
                // Mirror the framework: the administrator role holds everything.
                return in_array('administrator', $this->roles, true)
                    || in_array($permission, $this->permissions, true);
            }

            public function getRoles(): array
            {
                return $this->roles;
            }

            public function isAuthenticated(): bool
            {
                return $this->authenticated;
            }
        };
    }

    #[Test]
    public function the_three_roles_are_defined_with_the_right_permissions(): void
    {
        $roles = WorkspaceAccess::roles();
        $this->assertArrayHasKey('administrator', $roles);
        $this->assertArrayHasKey('editor', $roles);
        $this->assertArrayHasKey('viewer', $roles);

        // Editor: the three edit permissions plus the coarse agent-tool
        // capabilities (the policy is the decisive gate).
        foreach (['edit identity', 'edit documents', 'edit drive', 'tool.entity.update', 'tool.entity.create'] as $perm) {
            $this->assertContains($perm, $roles['editor']['permissions']);
        }
        $this->assertNotContains('administer identity', $roles['editor']['permissions']);
        // Viewer: only the agent-tool capabilities, no edit/administer rights.
        $this->assertContains('tool.entity.read', $roles['viewer']['permissions']);
        $this->assertNotContains('edit identity', $roles['viewer']['permissions']);
        $this->assertContains('administer identity', $roles['administrator']['permissions']);
        $this->assertContains('administer drive', $roles['administrator']['permissions']);
    }

    #[Test]
    public function apply_admin_uses_the_builtin_administrator_role(): void
    {
        $user = new User(['uid' => 10, 'name' => 'Admin Person']);
        WorkspaceAccess::apply($user, WorkspaceAccess::ROLE_ADMIN);

        $this->assertContains('administrator', $user->getRoles());
        // Administrator short-circuits every permission.
        $this->assertTrue($user->hasPermission('edit identity'));
        $this->assertTrue($user->hasPermission('administer documents'));
        $this->assertTrue($user->hasPermission('anything at all'));
    }

    #[Test]
    public function apply_editor_grants_edit_but_not_administer(): void
    {
        $user = new User(['uid' => 11, 'name' => 'Editor Person']);
        WorkspaceAccess::apply($user, WorkspaceAccess::ROLE_EDITOR);

        $this->assertContains('editor', $user->getRoles());
        $this->assertTrue($user->hasPermission('edit identity'));
        $this->assertTrue($user->hasPermission('edit documents'));
        $this->assertTrue($user->hasPermission('edit drive'));
        $this->assertFalse($user->hasPermission('administer identity'));
        $this->assertFalse($user->hasPermission('administer documents'));
        $this->assertFalse($user->hasPermission('administer drive'));
    }

    #[Test]
    public function apply_viewer_grants_no_writes(): void
    {
        $user = new User(['uid' => 12, 'name' => 'Viewer Person']);
        WorkspaceAccess::apply($user, WorkspaceAccess::ROLE_VIEWER);

        $this->assertContains('viewer', $user->getRoles());
        $this->assertFalse($user->hasPermission('edit identity'));
        $this->assertFalse($user->hasPermission('edit documents'));
    }

    #[Test]
    public function apply_preserves_non_workspace_roles_and_replaces_the_workspace_role(): void
    {
        $user = new User(['uid' => 13, 'name' => 'Mixed', 'roles' => ['external_partner', 'editor']]);
        WorkspaceAccess::apply($user, WorkspaceAccess::ROLE_VIEWER);

        $roles = $user->getRoles();
        $this->assertContains('external_partner', $roles, 'non-workspace role kept');
        $this->assertContains('viewer', $roles, 'new workspace role applied');
        $this->assertNotContains('editor', $roles, 'old workspace role replaced');
    }

    #[Test]
    public function admin_and_editor_may_write_all_three_entities(): void
    {
        $handler = WorkspaceAccess::handler();
        foreach (['administrator role' => $this->account(true, [], ['administrator']), 'editor perms' => $this->account(true, ['edit identity', 'edit documents', 'edit drive'])] as $who => $account) {
            $this->assertTrue($handler->check(new Pillar(), 'update', $account)->isAllowed(), "$who pillar update");
            $this->assertTrue($handler->check(new Document(), 'update', $account)->isAllowed(), "$who document update");
            $this->assertTrue($handler->checkCreateAccess('document', '', $account)->isAllowed(), "$who document create");
            $this->assertTrue($handler->checkCreateAccess('document_note', '', $account)->isAllowed(), "$who note create");
            $this->assertTrue($handler->check(new DriveFile(), 'update', $account)->isAllowed(), "$who drive update");
            $this->assertTrue($handler->checkCreateAccess('drive_asset', '', $account)->isAllowed(), "$who drive upload");
        }
    }

    #[Test]
    public function viewer_is_read_only(): void
    {
        $handler = WorkspaceAccess::handler();
        $viewer = $this->account(true, []); // authenticated, no permissions

        // May read.
        $this->assertTrue($handler->check(new Pillar(), 'view', $viewer)->isAllowed());
        $this->assertTrue($handler->check(new Document(), 'view', $viewer)->isAllowed());
        $this->assertTrue($handler->check(new DriveFile(), 'view', $viewer)->isAllowed());
        // May not write.
        $this->assertFalse($handler->check(new Pillar(), 'update', $viewer)->isAllowed());
        $this->assertFalse($handler->check(new Document(), 'update', $viewer)->isAllowed());
        $this->assertFalse($handler->checkCreateAccess('document_note', '', $viewer)->isAllowed());
        $this->assertFalse($handler->checkCreateAccess('drive_asset', '', $viewer)->isAllowed());
    }

    #[Test]
    public function editor_can_edit_drive_but_not_delete(): void
    {
        $handler = WorkspaceAccess::handler();
        $editor = $this->account(true, ['edit identity', 'edit documents', 'edit drive']);
        $admin = $this->account(true, [], ['administrator']);

        // Editor uploads/edits Drive, but Drive deletes are the administer tier.
        $this->assertTrue($handler->checkCreateAccess('drive_asset', '', $editor)->isAllowed());
        $this->assertTrue($handler->check(new DriveFile(), 'update', $editor)->isAllowed());
        $this->assertFalse($handler->check(new DriveFile(), 'delete', $editor)->isAllowed(), 'editor cannot delete Drive files');
        $this->assertTrue($handler->check(new DriveFile(), 'delete', $admin)->isAllowed(), 'admin can delete Drive files');
    }

    #[Test]
    public function anonymous_cannot_even_read(): void
    {
        $handler = WorkspaceAccess::handler();
        $anon = $this->account(false, []);
        $this->assertFalse($handler->check(new Pillar(), 'view', $anon)->isAllowed());
        $this->assertFalse($handler->check(new Document(), 'view', $anon)->isAllowed());
    }

    #[Test]
    public function delete_requires_admin_not_editor(): void
    {
        $handler = WorkspaceAccess::handler();
        $editor = $this->account(true, ['edit identity', 'edit documents']);
        $admin = $this->account(true, [], ['administrator']);

        $this->assertFalse($handler->check(new Pillar(), 'delete', $editor)->isAllowed(), 'editor cannot delete');
        $this->assertFalse($handler->check(new Document(), 'delete', $editor)->isAllowed());
        $this->assertTrue($handler->check(new Pillar(), 'delete', $admin)->isAllowed(), 'admin can delete');
        $this->assertTrue($handler->check(new DocumentNote(), 'delete', $admin)->isAllowed());
    }
}
