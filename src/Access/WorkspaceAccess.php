<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\User\User;

/**
 * The Anokii workspace role and permission model, mapped onto Waaseyaa's
 * existing role/permission substrate (no parallel system).
 *
 * Three roles:
 *   - Admin  -> the framework's built-in `administrator` role, which short
 *               circuits every permission check. Holds all workspace permissions.
 *   - Editor -> the `editor` role plus the two `edit ...` permissions: full
 *               read/write on Identity and Documents, no destructive ops.
 *   - Viewer -> the `viewer` role with no write permissions: read-only.
 *
 * Because `User::hasPermission()` does not union a role's permissions (only the
 * `administrator` role is special-cased), a non-admin role is applied by writing
 * its concrete permission strings onto the user's permissions list. apply()
 * does exactly that, so the role and its permissions always travel together.
 *
 * The AccessPolicy classes (one per entity) are the single source of truth for
 * what each permission grants; both the UI controllers (via handler()) and, in
 * a later increment, the agent tools consult the same policies.
 */
final class WorkspaceAccess
{
    // Roles. Admin reuses the framework's all-permissions role.
    public const string ROLE_ADMIN = 'administrator';
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
    public const string PUBLISH_PAGES = 'publish pages';
    public const string ADMINISTER_PAGES = 'administer pages';

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

    /** @return list<string> every workspace permission */
    public static function allPermissions(): array
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
            ...self::AGENT_TOOL_CAPABILITIES,
        ];
    }

    /**
     * Role definitions: id => {label, permissions}. Editor and Viewer are
     * defined and available but assigned to no one until staff/Council/external
     * accounts arrive.
     *
     * @return array<string, array{label:string, permissions:list<string>}>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN => ['label' => 'Admin', 'permissions' => self::allPermissions()],
            self::ROLE_EDITOR => ['label' => 'Editor', 'permissions' => [self::EDIT_IDENTITY, self::EDIT_DOCUMENTS, self::EDIT_DRIVE, self::EDIT_PAGES, self::PUBLISH_PAGES, ...self::AGENT_TOOL_CAPABILITIES]],
            self::ROLE_VIEWER => ['label' => 'Viewer', 'permissions' => [...self::AGENT_TOOL_CAPABILITIES]],
        ];
    }

    public static function isRole(string $roleId): bool
    {
        return array_key_exists($roleId, self::roles());
    }

    /**
     * Apply a workspace role to a user: set the role and write its permissions,
     * preserving any roles that are not part of the workspace model. The caller
     * persists the user.
     */
    public static function apply(User $user, string $roleId): void
    {
        $defs = self::roles();
        $permissions = $defs[$roleId]['permissions'] ?? [];

        // Keep any non-workspace roles; replace the workspace role with this one.
        $kept = array_values(array_filter(
            $user->getRoles(),
            static fn(string $role): bool => !array_key_exists($role, $defs),
        ));
        $user->setRoles(array_values(array_unique([...$kept, $roleId])));
        $user->setPermissions($permissions);
    }

    /**
     * The single construction point for the workspace access handler: the three
     * entity policies. Reused by the UI controllers and the tests so there is
     * one source of truth.
     */
    public static function handler(): EntityAccessHandler
    {
        return new EntityAccessHandler([
            new IdentityPillarAccessPolicy(),
            new DocumentAccessPolicy(),
            new DocumentNoteAccessPolicy(),
            new DriveFileAccessPolicy(),
            new PageAccessPolicy(),
        ]);
    }
}
