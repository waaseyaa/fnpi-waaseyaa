<?php

declare(strict_types=1);

namespace App\Anokii;

/**
 * The Anokii module set, mirroring the demo's nav/dashboard structure.
 *
 * Dashboard and Identity Workspace are live; the rest carry a "soon" badge and
 * open a coming-soon placeholder, so the workspace shows the full vision. Icons
 * are inline SVG inner markup (line icons; brand-neutral). `tile` = shown on the
 * dashboard grid; Dashboard and Settings are nav-only.
 *
 * @phpstan-type Module array{id:string,label:string,group:string,live:bool,href:string,desc:string,icon:string,badge:string,tile:bool}
 */
final class Modules
{
    /** @return list<array{id:string,label:string,group:string,live:bool,href:string,desc:string,icon:string,badge:string,tile:bool}> */
    public static function all(): array
    {
        return [
            [
                'id' => 'home', 'label' => 'Dashboard', 'group' => 'Workspace', 'live' => true,
                'href' => '/anokii', 'desc' => '', 'badge' => '', 'tile' => false,
                'icon' => '<path d="M4 13h7V4H4v9Zm0 7h7v-5H4v5Zm9 0h7v-9h-7v9Zm0-16v5h7V4h-7Z" fill="currentColor"/>',
            ],
            [
                'id' => 'identity', 'label' => 'Identity Workspace', 'group' => 'Workspace', 'live' => true,
                'href' => '/anokii/identity', 'desc' => 'Define who FNPI is, pillar by pillar.', 'badge' => '', 'tile' => true,
                'icon' => '<circle cx="12" cy="9" r="3.2" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M5 20c1.2-3.6 4-5.4 7-5.4s5.8 1.8 7 5.4" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round"/>',
            ],
            [
                'id' => 'drive', 'label' => 'Drive', 'group' => 'Workspace', 'live' => true,
                'href' => '/anokii/drive', 'desc' => 'Department file storage, scoped to the Nation.', 'badge' => '', 'tile' => true,
                'icon' => '<path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" stroke="currentColor" stroke-width="1.7" fill="none"/>',
            ],
            [
                'id' => 'documents', 'label' => 'Documents', 'group' => 'Workspace', 'live' => true,
                'href' => '/anokii/documents', 'desc' => 'Preview, version, and discuss documents in one place.', 'badge' => '', 'tile' => true,
                'icon' => '<path d="M7 3h7l4 4v14a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linejoin="round"/><path d="M14 3v4h4" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linejoin="round"/><path d="M9 13h6M9 16h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
            ],
            [
                'id' => 'ai', 'label' => 'Co-Intelligence', 'group' => 'Workspace', 'live' => true,
                'href' => '/anokii/cointelligence', 'desc' => 'Ask questions of your own documents and decisions.', 'badge' => '', 'tile' => true,
                'icon' => '<path d="M5 5h14v10H8l-3 3V5Z" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linejoin="round"/><circle cx="9" cy="10" r="1" fill="currentColor"/><circle cx="12" cy="10" r="1" fill="currentColor"/><circle cx="15" cy="10" r="1" fill="currentColor"/>',
            ],
            [
                'id' => 'pages', 'label' => 'Pages', 'group' => 'Workspace', 'live' => true,
                'href' => '/anokii/pages', 'desc' => 'Edit and publish the public website, with full revision history.', 'badge' => '', 'tile' => true,
                'icon' => '<rect x="4" y="4" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M4 9h16" stroke="currentColor" stroke-width="1.7"/><path d="M8 13h8M8 16h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
            ],
            [
                'id' => 'rooms', 'label' => 'Data Rooms', 'group' => 'Workspace', 'live' => false,
                'href' => '/anokii/m/rooms', 'desc' => 'Secure, time-bound spaces with full audit trails.', 'badge' => 'Soon', 'tile' => true,
                'icon' => '<rect x="4" y="5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M4 9h16" stroke="currentColor" stroke-width="1.7"/><circle cx="15" cy="14" r="2" stroke="currentColor" stroke-width="1.6" fill="none"/>',
            ],
            [
                'id' => 'workspaces', 'label' => 'Workspaces', 'group' => 'Workspace', 'live' => false,
                'href' => '/anokii/m/workspaces', 'desc' => "Run projects without them living in someone's inbox.", 'badge' => 'Soon', 'tile' => true,
                'icon' => '<rect x="4" y="4" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.7" fill="none"/><rect x="13" y="4" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.7" fill="none"/><rect x="4" y="13" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.7" fill="none"/><rect x="13" y="13" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.7" fill="none"/>',
            ],
            [
                'id' => 'portal', 'label' => 'Portal', 'group' => 'Workspace', 'live' => false,
                'href' => '/anokii/m/portal', 'desc' => 'Run the public website and member portal from one place.', 'badge' => 'Soon', 'tile' => true,
                'icon' => '<circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M4 12h16M12 4c2.4 2.6 2.4 13.4 0 16M12 4c-2.4 2.6-2.4 13.4 0 16" stroke="currentColor" stroke-width="1.4" fill="none"/>',
            ],
            [
                'id' => 'vault', 'label' => 'Vault', 'group' => 'Administration', 'live' => false,
                'href' => '/anokii/m/vault', 'desc' => 'Credentials and confidential records, locked down.', 'badge' => 'Soon', 'tile' => true,
                'icon' => '<rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.7" fill="none"/>',
            ],
            [
                'id' => 'governance', 'label' => 'Governance', 'group' => 'Administration', 'live' => false,
                'href' => '/anokii/m/governance', 'desc' => 'See who has access and where data lives.', 'badge' => 'Soon', 'tile' => true,
                'icon' => '<path d="M12 4 4 7v5c0 4 3.5 7 8 8 4.5-1 8-4 8-8V7l-8-3Z" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linejoin="round"/>',
            ],
            [
                'id' => 'settings', 'label' => 'Settings', 'group' => 'Administration', 'live' => true,
                'href' => '/anokii/settings', 'desc' => '', 'badge' => '', 'tile' => false,
                'icon' => '<circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1" stroke="currentColor" stroke-width="1.5"/>',
            ],
        ];
    }

    /** @return array{id:string,label:string,group:string,live:bool,href:string,desc:string,icon:string,badge:string,tile:bool}|null */
    public static function find(string $id): ?array
    {
        foreach (self::all() as $m) {
            if ($m['id'] === $id) {
                return $m;
            }
        }

        return null;
    }
}
