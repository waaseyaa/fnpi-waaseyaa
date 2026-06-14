<?php

declare(strict_types=1);

namespace App\Access;

use Anokii\Access\AbstractWorkspaceRoles;
use Waaseyaa\Access\EntityAccessHandler;

/**
 * The Anokii workspace role and permission model for FNPI, mapped onto
 * Waaseyaa's existing role/permission substrate (no parallel system).
 *
 * Subclasses the shared Anokii role base ({@see AbstractWorkspaceRoles}): the
 * base derives apply() (which returns the updated User), the framework role
 * value objects for discovery (ProvidesRolesInterface, exposed by
 * AnokiiServiceProvider), the permission union, the role-label map, and the
 * isRole/permissionsFor accessors from the single roleDefinitions() declaration
 * below. FNPI declares only its three roles and keeps its permission string
 * constants and the per-entity access handler.
 *
 * Three roles:
 *   - Admin  -> the framework's built-in `administrator` role, which short
 *               circuits every permission check. Holds all workspace permissions.
 *   - Editor -> the edit-and-publish permissions: full read/write on Identity,
 *               Documents, Drive, Pages, the inbox, and the venture numbers, but
 *               no destructive (administer) ops.
 *   - Viewer -> the coarse agent-tool capabilities only: read-only to content.
 *
 * Because the framework's User::hasPermission() does not union a role's
 * permissions (only the `administrator` role is special-cased), a non-admin
 * role is applied by writing its concrete permission strings onto the user's
 * permissions list. The base apply() does that; the framework user:assign-role
 * command does the same via the discovered roles (it replaced the bespoke
 * app:assign-role command).
 */
final class WorkspaceAccess extends AbstractWorkspaceRoles
{
    // Roles. Admin reuses the framework's all-permissions role.
    public const string ROLE_ADMIN = self::ROLE_ADMINISTRATOR;
    public const string ROLE_EDITOR = 'editor';
    public const string ROLE_VIEWER = 'viewer';

    // Permissions.
    public const string EDIT_IDENTITY = 'edit identity';
    public const string ADMINISTER_IDENTITY = 'administer identity';
    public const string EDIT_DOCUMENTS = 'edit documents';
    public const string ADMINISTER_DOCUMENTS = 'administer documents';
    public const string EDIT_DRIVE = 'edit drive';
    public const string ADMINISTER_DRIVE = 'administer drive';
    public const string EDIT_PAGES = 'edit pages';
    public const string MANAGE_INBOX = 'manage inbox';
    public const string PUBLISH_PAGES = 'publish pages';
    public const string ADMINISTER_PAGES = 'administer pages';
    // Venture Numbers: view is itself permission-gated (the section is
    // staff-only; the Viewer role does NOT get it).
    public const string VIEW_VENTURES = 'view ventures';
    public const string EDIT_VENTURES = 'edit ventures';
    public const string CONFIRM_VENTURES = 'confirm ventures';
    public const string ADMINISTER_VENTURES = 'administer ventures';

    /**
     * Coarse capabilities the framework's entity agent tools require before they
     * run. We grant the full set to every workspace role so the per-entity
     * AccessPolicy (attached to the tools) is the single decisive gate: a Viewer
     * passes the capability check but the policy refuses any write, an Editor is
     * refused deletes, etc. Exactly the same outcome as the UI controllers.
     *
     * @var list<string>
     */
    public const array AGENT_TOOL_CAPABILITIES = [
        'tool.entity.read',
        'tool.entity.list',
        'tool.entity.search',
        'tool.entity.create',
        'tool.entity.update',
        'tool.entity.delete',
    ];

    /**
     * Role definitions: id => {label, permissions, weight}. Editor and Viewer are
     * defined and available but assigned to no one until staff/Council/external
     * accounts arrive.
     *
     * @return array<string, array{label: string, permissions: list<string>, weight?: int}>
     */
    protected function roleDefinitions(): array
    {
        return [
            self::ROLE_ADMIN => [
                'label' => 'Admin',
                'permissions' => self::adminPermissions(),
                'weight' => 0,
            ],
            self::ROLE_EDITOR => [
                'label' => 'Editor',
                'permissions' => [
                    self::EDIT_IDENTITY,
                    self::EDIT_DOCUMENTS,
                    self::EDIT_DRIVE,
                    self::EDIT_PAGES,
                    self::PUBLISH_PAGES,
                    self::MANAGE_INBOX,
                    self::VIEW_VENTURES,
                    self::EDIT_VENTURES,
                    self::CONFIRM_VENTURES,
                    ...self::AGENT_TOOL_CAPABILITIES,
                ],
                'weight' => 10,
            ],
            self::ROLE_VIEWER => [
                'label' => 'Viewer',
                'permissions' => [...self::AGENT_TOOL_CAPABILITIES],
                'weight' => 20,
            ],
        ];
    }

    /**
     * Every workspace permission, granted to the admin role. Listed explicitly
     * (not derived from the role union) so the admin definition does not depend
     * on the base allPermissions() reading roleDefinitions() while it is being
     * built.
     *
     * @return list<string>
     */
    private static function adminPermissions(): array
    {
        return [
            self::EDIT_IDENTITY,
            self::ADMINISTER_IDENTITY,
            self::EDIT_DOCUMENTS,
            self::ADMINISTER_DOCUMENTS,
            self::EDIT_DRIVE,
            self::ADMINISTER_DRIVE,
            self::EDIT_PAGES,
            self::PUBLISH_PAGES,
            self::ADMINISTER_PAGES,
            self::MANAGE_INBOX,
            self::VIEW_VENTURES,
            self::EDIT_VENTURES,
            self::CONFIRM_VENTURES,
            self::ADMINISTER_VENTURES,
            ...self::AGENT_TOOL_CAPABILITIES,
        ];
    }

    /**
     * The single construction point for the workspace access handler: the eight
     * entity policies. Reused by the UI controllers, the agent tools, and the
     * tests so there is one source of truth. (FNPI-specific; not part of the
     * shared role base.)
     */
    public static function handler(): EntityAccessHandler
    {
        return new EntityAccessHandler([
            new IdentityPillarAccessPolicy(),
            new DocumentAccessPolicy(),
            new DocumentNoteAccessPolicy(),
            new DriveFileAccessPolicy(),
            new PageAccessPolicy(),
            new ContactSubmissionAccessPolicy(),
            new VentureAccessPolicy(),
            new VentureTrackerAccessPolicy(),
        ]);
    }
}
