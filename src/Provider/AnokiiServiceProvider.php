<?php

declare(strict_types=1);

namespace App\Provider;

use App\Access\WorkspaceAccess;
use App\Auth\SetupTokenRepository;
use App\Auth\SetupTokenSchema;
use App\CLI\AnokiiInviteHandler;
use App\CoIntelligence\AgentConversation;
use App\CoIntelligence\AgentProposalRepository;
use App\CoIntelligence\AgentTools;
use App\CoIntelligence\ChatPromptBuilder;
use App\CoIntelligence\ChatSchema;
use App\CoIntelligence\ConversationRepository;
use App\CoIntelligence\DocChunkRepository;
use App\CoIntelligence\Retriever;
use App\Command\IngestKnowledgeCommand;
use App\Command\MigrateDriveCommand;
use App\Command\MigratePillarsCommand;
use App\Command\WidenPillarsCommand;
use App\Command\SeedDocumentsCommand;
use App\Command\SeedDriveCommand;
use App\Command\SeedVenturesCommand;
use App\Controller\AnokiiController;
use App\Controller\CoIntelligenceController;
use App\Controller\DocumentsController;
use App\Controller\DriveController;
use App\Controller\IdentityController;
use App\Controller\PagesController;
use App\Controller\VenturesController;
use App\Venture\VentureService;
use App\Documents\DocumentService;
use App\Documents\DocumentStorage;
use App\Documents\GotenbergClient;
use App\Drive\DriveFileService;
use App\Drive\DriveStorage;
use App\Identity\PillarService;
use App\Pages\CloudflareCachePurger;
use App\Pages\PagesService;
use App\Pages\PublishedPageRenderer;
use App\Support\Db;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\AI\Agent\Provider\AnthropicProvider;
use Waaseyaa\AI\Agent\Provider\NullLlmProvider;
use Waaseyaa\AI\Agent\Provider\ProviderInterface;
use Waaseyaa\CLI\Command\HandlerArgument;
use Waaseyaa\CLI\Command\HandlerArgumentMode;
use Waaseyaa\CLI\Command\HandlerCommand;
use Waaseyaa\CLI\Command\HandlerOption;
use Waaseyaa\CLI\Command\HandlerOptionMode;
use Waaseyaa\CLI\Command\SymfonyCommandIO;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Foundation\ServiceProvider\Capability\ProvidesConsoleCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\Capability\ProvidesRolesInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Wires the authenticated Anokii workspace at /admin/anokii/*: the shell (login,
 * logout, dashboard, settings, set-password) and tool #1 (Identity Workspace).
 *
 * Routes are registered ->allowAll() at the framework layer; each controller
 * enforces the session itself and redirects unauthenticated page requests to
 * /admin/anokii/login (and returns 401 for JSON actions), so the gate's redirect
 * target is exactly /admin/anokii/login. Public marketing routes live in
 * SiteServiceProvider and are untouched.
 */
final class AnokiiServiceProvider extends ServiceProvider implements ProvidesConsoleCommandsInterface, ProvidesRolesInterface
{
    /** Chat model, matching oiatc's Co-Intelligence (current Claude Sonnet). */
    private const CHAT_MODEL = 'claude-sonnet-4-6';

    /**
     * Route priority for the workspace. Must beat the admin SPA's GET catch-all
     * at /admin/{path} (waaseyaa/admin-surface, priority 0) so /admin/anokii/*
     * resolves to these controllers and not the SPA shell.
     */
    private const ROUTE_PRIORITY = 100;

    private ?DatabaseInterface $db = null;

    public function register(): void {}

    public function boot(): void
    {
        // Ensure schema + seed on the persistent file, gated so routing-only
        // unit tests (no kernel) skip it. Wrapped so a storage hiccup never
        // takes down a page.
        if (!$this->kernelPresent()) {
            return;
        }
        try {
            $db = $this->db();
            new SetupTokenSchema($db)->ensure();
            new ChatSchema($db)->ensure();
            new AgentProposalRepository($db)->ensure();
            // Identity pillars and Drive files are entities now (identity_pillar,
            // drive_asset): their tables are materialized by db:init
            // --sync-schema and populated once via app:migrate-pillars /
            // app:migrate-drive, not ensured/seeded at boot.
        } catch (\Throwable) {
            // best effort; the tool surfaces an empty state rather than 500ing
        }

        $this->registerPackageTemplates();
    }

    /**
     * Make the shared Anokii package templates resolvable on the SSR Twig
     * environment (its loader is a ChainLoader of FilesystemLoaders, scanning the
     * app's own templates only). Registers the package dir both unprefixed (so
     * `anokii/...` package templates resolve after the app's own) and under the
     * `@anokiipkg` namespace (so `_fnpi_base` can extend `@anokiipkg/_shell.html.twig`
     * unambiguously, even while the forked anokii/_shell.html.twig still exists).
     * One spot; covers every tool controller (they all render via this shared env).
     */
    private function registerPackageTemplates(): void
    {
        try {
            $twig = \Waaseyaa\SSR\SsrServiceProvider::getTwigEnvironment();
            if ($twig === null) {
                return;
            }
            $pkg = \Anokii\Admin\AdminTemplates::path();
            $loader = $twig->getLoader();
            if ($loader instanceof \Twig\Loader\ChainLoader) {
                $fs = new \Twig\Loader\FilesystemLoader();
                $fs->addPath($pkg);
                $fs->addPath($pkg . '/anokii', 'anokiipkg');
                $loader->addLoader($fs);
            } elseif ($loader instanceof \Twig\Loader\FilesystemLoader) {
                $loader->addPath($pkg);
                $loader->addPath($pkg . '/anokii', 'anokiipkg');
            }
        } catch (\Throwable) {
            // best effort; if the SSR env is not up yet the page falls back as before
        }
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        // Workspace access: the three entity policies (Identity, Documents,
        // Document notes), the single source of truth the UI controllers consult
        // (and the agent tools will consult in a later increment).
        $access = WorkspaceAccess::handler();

        $shell = new AnokiiController($entityTypeManager, new SetupTokenRepository($this->db()));
        $identity = new IdentityController($entityTypeManager, new PillarService($entityTypeManager), $access);

        // Co-Intelligence (tool #2): construct the model provider directly from the
        // server-side key (mirrors oiatc; resolve() at route-build can hand back an
        // ephemeral binding). NullLlmProvider keeps the page working when no key is
        // set, with the controller reporting "not configured" instead of erroring.
        $anthropicKey = getenv('ANTHROPIC_API_KEY') ?: '';
        $configured = $anthropicKey !== '';
        $provider = $configured
            ? new AnthropicProvider($anthropicKey, self::CHAT_MODEL)
            : new NullLlmProvider();
        $prompts = new ChatPromptBuilder();
        $conversations = new ConversationRepository($this->db());
        $proposals = new AgentProposalRepository($this->db());

        // Agentic mode (confirm-before-apply CRUD over the workspace): only when
        // a model is configured AND the flag is set. Off by default, so the live
        // chat stays read-only grounded RAG until explicitly enabled. The agent
        // tools consult WorkspaceAccess (the same policies as the UI).
        $agentEnabled = $this->agentToolsEnabled();
        $agent = ($configured && $agentEnabled)
            ? new AgentConversation($provider, new AgentTools($entityTypeManager), $proposals, $conversations, $prompts)
            : null;

        $cointel = new CoIntelligenceController(
            $entityTypeManager,
            new Retriever($this->db()),
            $prompts,
            $conversations,
            $provider,
            $configured,
            $agent,
            $proposals,
            $agentEnabled,
        );

        // Drive (tool #3): entity-native file storage. Bytes go to the sovereign
        // volume via the media layer; the revisionable `drive_asset` entity
        // carries metadata + attribution and falls under the same AccessPolicy.
        $drive = new DriveController(
            $entityTypeManager,
            new DriveFileService($entityTypeManager),
            new DriveStorage(
                $this->filesDir(),
                $this->allowedUploadMimeTypes(),
                $this->uploadMaxBytes(),
            ),
            $access,
        );

        // Documents (tool #4): the first entity-native tool. Bytes go to the
        // sovereign volume via the media layer; the revisionable `document`
        // entity carries each version as a revision; Gotenberg converts uploaded
        // .docx to a .pdf preview (lean image, conversion out of process).
        $docStorage = new DocumentStorage(
            $this->filesDir(),
            ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            $this->uploadMaxBytes(),
        );
        $documents = new DocumentsController(
            $entityTypeManager,
            new DocumentService($entityTypeManager, $docStorage, new GotenbergClient($this->gotenbergUrl())),
            $docStorage,
            $access,
        );

        // Pages (tool #5): the workspace editor for the public marketing `page`
        // entities. Draft/preview/publish/rollback over the revision +
        // published-pointer model; gated by the same AccessPolicy (edit pages /
        // publish pages).
        $pages = new PagesController($entityTypeManager, new PagesService($entityTypeManager, new CloudflareCachePurger()), $access);

        // Venture Numbers (staff-only): the revenue model mirrored as
        // revisionable entities, chat-first beside Co-Intelligence. The first
        // tool whose READ is permission-gated (view ventures, Forbidden
        // otherwise via VentureAccessPolicy).
        $ventures = new VenturesController($entityTypeManager, new VentureService($entityTypeManager), $access);

        // The workspace lives UNDER /admin/anokii, but the admin SPA
        // (waaseyaa/admin-surface) owns a GET catch-all at /admin/{path}
        // (priority 0, registered by the framework before app providers). These
        // explicit routes must win, so they register at a higher priority —
        // WaaseyaaRouter::sortRoutesByPriority() (run once at boot) orders
        // higher-first, and the first UrlMatcher hit wins. Mirrors the
        // framework's own `->priority()` override pattern.
        $get = static fn(string $name, string $path, callable $c) => $router->addRoute(
            $name,
            RouteBuilder::create($path)->controller($c)->allowAll()->methods('GET')->priority(self::ROUTE_PRIORITY)->build(),
        );
        $post = static fn(string $name, string $path, callable $c) => $router->addRoute(
            $name,
            RouteBuilder::create($path)->controller($c)->allowAll()->methods('POST')->priority(self::ROUTE_PRIORITY)->build(),
        );

        $get('anokii.home', '/admin/anokii', fn(Request $r) => $shell->dashboard($r));
        $get('anokii.login', '/admin/anokii/login', fn(Request $r) => $shell->loginForm($r));
        $post('anokii.login.post', '/admin/anokii/login', fn(Request $r) => $shell->loginSubmit($r));
        $get('anokii.logout', '/admin/anokii/logout', fn(Request $r) => $shell->logout($r));
        $get('anokii.settings', '/admin/anokii/settings', fn(Request $r) => $shell->settings($r));
        $post('anokii.settings.post', '/admin/anokii/settings', fn(Request $r) => $shell->settingsSave($r));
        $get('anokii.setpw', '/admin/anokii/set-password', fn(Request $r) => $shell->setPasswordForm($r));
        $post('anokii.setpw.post', '/admin/anokii/set-password', fn(Request $r) => $shell->setPasswordSubmit($r));
        $get('anokii.identity', '/admin/anokii/identity', fn(Request $r) => $identity->index($r));
        $post('anokii.identity.save', '/admin/anokii/identity/save', fn(Request $r) => $identity->save($r));
        $get('anokii.identity.history', '/admin/anokii/identity/{pid}/history', fn(Request $r, string $pid) => $identity->history($r, $pid));
        $post('anokii.identity.translate', '/admin/anokii/identity/translate', fn(Request $r) => $identity->saveTranslation($r));
        $get('anokii.identity.translation_history', '/admin/anokii/identity/{pid}/{langcode}/history', fn(Request $r, string $pid, string $langcode) => $identity->translationHistory($r, $pid, $langcode));
        $get('anokii.pages', '/admin/anokii/pages', fn(Request $r) => $pages->index($r));
        $get('anokii.pages.edit', '/admin/anokii/pages/{id}', fn(Request $r, string $id) => $pages->edit($r, $id));
        $get('anokii.pages.preview', '/admin/anokii/pages/{id}/preview', fn(Request $r, string $id) => $pages->preview($r, $id));
        $get('anokii.pages.history', '/admin/anokii/pages/{id}/history', fn(Request $r, string $id) => $pages->history($r, $id));
        $post('anokii.pages.save', '/admin/anokii/pages/{id}/save', fn(Request $r, string $id) => $pages->save($r, $id));
        $post('anokii.pages.publish', '/admin/anokii/pages/{id}/publish', fn(Request $r, string $id) => $pages->publish($r, $id));
        $post('anokii.pages.rollback', '/admin/anokii/pages/{id}/rollback', fn(Request $r, string $id) => $pages->rollback($r, $id));
        $get('anokii.cointelligence', '/admin/anokii/cointelligence', fn(Request $r) => $cointel->index($r));
        $post('anokii.cointelligence.send', '/admin/anokii/cointelligence/send', fn(Request $r) => $cointel->send($r));
        $post('anokii.cointelligence.apply', '/admin/anokii/cointelligence/apply', fn(Request $r) => $cointel->apply($r));
        $get('anokii.cointelligence.messages', '/admin/anokii/cointelligence/{id}/messages', fn(Request $r, string $id) => $cointel->messages($r, $id));
        $get('anokii.drive', '/admin/anokii/drive', fn(Request $r) => $drive->index($r));
        $post('anokii.drive.upload', '/admin/anokii/drive/upload', fn(Request $r) => $drive->upload($r));
        $get('anokii.drive.file', '/admin/anokii/drive/file/{id}', fn(Request $r, string $id) => $drive->download($r, $id));
        $post('anokii.drive.delete', '/admin/anokii/drive/delete', fn(Request $r) => $drive->delete($r));
        $analytics = new \App\Controller\AnokiiAnalyticsController($entityTypeManager, new \App\Analytics\AnalyticsReport($this->db()));
        $get('anokii.analytics', '/admin/anokii/analytics', fn(Request $r) => $analytics->index($r));

        $get('anokii.ventures', '/admin/anokii/ventures', fn(Request $r) => $ventures->index($r));
        $post('anokii.ventures.lane_save', '/admin/anokii/ventures/lane/save', fn(Request $r) => $ventures->saveLane($r));
        $post('anokii.ventures.fact_save', '/admin/anokii/ventures/fact/save', fn(Request $r) => $ventures->saveFact($r));
        $get('anokii.ventures.lane_history', '/admin/anokii/ventures/lane/{key}/history', fn(Request $r, string $key) => $ventures->laneHistory($r, $key));
        $get('anokii.ventures.fact_history', '/admin/anokii/ventures/fact/{key}/history', fn(Request $r, string $key) => $ventures->factHistory($r, $key));

        $venture = new \App\Controller\VentureController($entityTypeManager);
        $get('anokii.venture', '/admin/anokii/venture', fn(Request $r) => $venture->index($r));

        $inbox = new \App\Controller\ContactInboxController($entityTypeManager, $access);
        $get('anokii.inbox', '/admin/anokii/inbox', fn(Request $r) => $inbox->index($r));
        $post('anokii.inbox.read', '/admin/anokii/inbox/read', fn(Request $r) => $inbox->markAllRead($r));

        $get('anokii.documents', '/admin/anokii/documents', fn(Request $r) => $documents->index($r));
        $post('anokii.documents.create', '/admin/anokii/documents/create', fn(Request $r) => $documents->create($r));
        $get('anokii.documents.show', '/admin/anokii/documents/{uuid}', fn(Request $r, string $uuid) => $documents->show($r, $uuid));
        $post('anokii.documents.version', '/admin/anokii/documents/{uuid}/version', fn(Request $r, string $uuid) => $documents->uploadVersion($r, $uuid));
        $post('anokii.documents.setcurrent', '/admin/anokii/documents/{uuid}/set-current', fn(Request $r, string $uuid) => $documents->setCurrent($r, $uuid));
        $post('anokii.documents.rollback', '/admin/anokii/documents/{uuid}/rollback', fn(Request $r, string $uuid) => $documents->rollback($r, $uuid));
        $post('anokii.documents.note', '/admin/anokii/documents/{uuid}/note', fn(Request $r, string $uuid) => $documents->addNote($r, $uuid));
        $get('anokii.documents.file', '/admin/anokii/documents/{uuid}/file/{vid}/{kind}', fn(Request $r, string $uuid, string $vid, string $kind) => $documents->download($r, $uuid, $vid, $kind));
        // Coming-soon placeholder for not-yet-live modules (rooms, ...).
        $get('anokii.module', '/admin/anokii/m/{module}', fn(Request $r, string $module) => $shell->comingSoon($r, $module));

        // Legacy redirects: the workspace moved from /anokii/* to /admin/anokii/*
        // (the admin SPA now owns /admin, and the bare /anokii root is being
        // freed for a public marketing page). 301 every old SUB-path — login,
        // set-password, settings, identity*, cointelligence*, drive*, documents*,
        // pages*, inbox*, venture, ventures*, analytics, m/* — to its new home so
        // invite links and bookmarks resolve. The catch-all `{rest}` requires at
        // least one segment (requirement '.+', which also matches slashes), so the
        // bare /anokii root is intentionally NOT matched here: it 404s until the
        // marketing page ships. The original query string is preserved so the
        // one-time set-password ?token=… invite links keep working.
        $router->addRoute(
            'anokii.legacy_redirect',
            RouteBuilder::create('/anokii/{rest}')
                ->controller(static function (Request $r, string $rest): RedirectResponse {
                    $target = '/admin/anokii/' . $rest;
                    $query = $r->getQueryString();
                    if ($query !== null && $query !== '') {
                        $target .= '?' . $query;
                    }

                    return new RedirectResponse($target, 301);
                })
                ->allowAll()
                ->methods('GET')
                ->requirement('rest', '.+')
                ->priority(self::ROUTE_PRIORITY)
                ->build(),
        );
    }

    /**
     * Contribute the FNPI workspace roles to the framework RoleRepository so the
     * framework user:assign-role command can resolve them and stamp their
     * permissions. Delegates to the WorkspaceAccess role model (the single
     * source of truth). Replaces the bespoke app:assign-role command.
     *
     * @return iterable<\Waaseyaa\User\Role>
     */
    public function roles(): iterable
    {
        yield from new WorkspaceAccess()->roles();
    }

    public function consoleCommands(): iterable
    {
        yield new HandlerCommand(
            name: 'anokii:invite',
            description: 'Create (if needed) an Anokii account and print a one-time set-password link',
            arguments: [
                new HandlerArgument(
                    name: 'email',
                    mode: HandlerArgumentMode::Required,
                    description: 'Email address of the account to invite',
                ),
            ],
            options: [
                new HandlerOption(name: 'name', mode: HandlerOptionMode::Required, description: 'Display name for a new account'),
                new HandlerOption(name: 'base-url', mode: HandlerOptionMode::Required, description: 'Base URL for the link (default https://fnprocure.ca)'),
            ],
            handler: function (SymfonyCommandIO $io): int {
                $etm = $this->entityTypeManager();
                if ($etm === null) {
                    $io->error('anokii:invite requires a booted kernel (EntityTypeManager).');

                    return 1;
                }

                return new AnokiiInviteHandler($etm)->execute($io);
            },
        );

        yield new HandlerCommand(
            name: 'app:ingest-knowledge',
            description: 'Build the Co-Intelligence RAG knowledge base from FNPI docs, the live site copy, and the Identity pillars.',
            options: [
                new HandlerOption(name: 'dry-run', mode: HandlerOptionMode::None, description: 'Preview extracted chunks without writing.'),
                new HandlerOption(name: 'prune', mode: HandlerOptionMode::Negatable, description: 'Delete stored chunks no longer present (use --no-prune to keep).', default: true),
            ],
            handler: function (SymfonyCommandIO $io): int {
                $etm = $this->entityTypeManager();
                if ($etm === null) {
                    $io->error('Knowledge ingest requires a booted kernel (EntityTypeManager).');

                    return 1;
                }
                $db = $this->db();
                new ChatSchema($db)->ensure();
                $twig = SsrServiceProvider::createTwigEnvironment($this->projectRoot, $this->config);
                $command = new IngestKnowledgeCommand(
                    new DocChunkRepository($db),
                    new PillarService($etm),
                    new PublishedPageRenderer($etm->getRepository('page'), $twig),
                    $this->projectRoot . '/resources/knowledge',
                );

                return $command->run($io);
            },
        );

        yield new HandlerCommand(
            name: 'app:seed-drive',
            description: 'Seed Drive with a directory of images. Bytes go to the sovereign volume via the media layer; one index row per file. Idempotent.',
            options: [
                new HandlerOption(name: 'dir', mode: HandlerOptionMode::Required, description: 'Directory of images to import (required).'),
                new HandlerOption(name: 'folder', mode: HandlerOptionMode::Required, description: 'Target folder / tag (default "Global relationships").'),
                new HandlerOption(name: 'owner-email', mode: HandlerOptionMode::Required, description: 'Attribute uploads to this account when it exists.'),
                new HandlerOption(name: 'owner-id', mode: HandlerOptionMode::Required, description: 'Fallback owner uid (default 1).'),
                new HandlerOption(name: 'owner-label', mode: HandlerOptionMode::Required, description: 'Fallback display name (default "Matthew Owl").'),
            ],
            handler: function (SymfonyCommandIO $io): int {
                $etm = $this->entityTypeManager();
                if ($etm === null) {
                    $io->error('Drive seed requires a booted kernel (EntityTypeManager).');

                    return 1;
                }
                $command = new SeedDriveCommand(
                    new DriveFileService($etm),
                    new DriveStorage($this->filesDir(), $this->allowedUploadMimeTypes(), $this->uploadMaxBytes()),
                    $etm,
                );

                return $command->run($io);
            },
        );

        yield new HandlerCommand(
            name: 'app:seed-documents',
            description: 'Seed the CANCOM document (3 versions + opening note) into the entity-native Documents tool. Idempotent.',
            options: [
                new HandlerOption(name: 'matthew-email', mode: HandlerOptionMode::Required, description: 'Account to attribute Matthew\'s versions to (default matthew@fnprocure.ca).'),
                new HandlerOption(name: 'russell-email', mode: HandlerOptionMode::Required, description: 'Account to attribute Russell\'s version + note to (default russell@fnprocure.ca).'),
            ],
            handler: function (SymfonyCommandIO $io): int {
                $etm = null;
                try {
                    $resolved = $this->resolve(\Waaseyaa\Entity\EntityTypeManager::class);
                    $etm = $resolved instanceof \Waaseyaa\Entity\EntityTypeManager ? $resolved : null;
                } catch (\Throwable) {
                    $io->error('Documents seed requires a booted kernel (EntityTypeManager).');

                    return 1;
                }
                if ($etm === null) {
                    $io->error('Documents seed requires a booted kernel (EntityTypeManager).');

                    return 1;
                }
                $storage = new DocumentStorage(
                    $this->filesDir(),
                    ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                    $this->uploadMaxBytes(),
                );
                $service = new DocumentService($etm, $storage, new GotenbergClient($this->gotenbergUrl()));
                $command = new SeedDocumentsCommand($service, $etm, $this->projectRoot . '/resources/seed/cancom');

                return $command->run($io);
            },
        );

        yield new HandlerCommand(
            name: 'app:seed-pages',
            description: 'Seed the five public pages (home, technology, how-it-works, contact, defence) into published `page` entities. Idempotent.',
            handler: function (SymfonyCommandIO $io): int {
                $etm = $this->entityTypeManager();
                if ($etm === null) {
                    $io->error('Pages seed requires a booted kernel (EntityTypeManager).');

                    return 1;
                }

                try {
                    $seeded = new \App\Pages\PageSeeder($etm->getRepository('page'))->seed();
                } catch (\Throwable $e) {
                    $io->error('Pages seed failed: ' . $e->getMessage());

                    return 1;
                }

                if ($seeded === []) {
                    $io->writeln('  skip   all pages already exist. Nothing to do.');
                } else {
                    foreach ($seeded as $path) {
                        $io->writeln(sprintf('  seed   %s published', $path));
                    }
                }

                return 0;
            },
        );

        yield new HandlerCommand(
            name: 'app:purge-cache',
            description: 'Purge the Cloudflare edge cache for the site zone (CLOUDFLARE_PURGE_TOKEN + CLOUDFLARE_ZONE_ID from the container env). No-op with a notice when unconfigured.',
            handler: static function (SymfonyCommandIO $io): int {
                $result = new CloudflareCachePurger()->purgeAll();
                if ($result === null) {
                    $io->writeln('  skip   purge not configured (set CLOUDFLARE_PURGE_TOKEN and CLOUDFLARE_ZONE_ID in fnpi.env); purge manually in the Cloudflare dashboard if needed.');

                    return 0;
                }
                if ($result === false) {
                    $io->error('Cloudflare purge FAILED (API error). Purge manually: dashboard > Caching > Configuration > Purge Everything.');

                    return 1;
                }
                $io->writeln('  ok     Cloudflare edge cache purged (purge_everything).');

                return 0;
            },
        );

        yield new HandlerCommand(
            name: 'app:migrate-pillars',
            description: 'Migrate the Identity Workspace from the raw pillar table to the entity-native identity_pillar entity, verbatim. One-time and idempotent.',
            handler: function (SymfonyCommandIO $io): int {
                $etm = $this->entityTypeManager();
                if ($etm === null) {
                    $io->error('Pillar migration requires a booted kernel (EntityTypeManager).');

                    return 1;
                }
                $command = new MigratePillarsCommand(new PillarService($etm), $this->db());

                return $command->run($io);
            },
        );

        yield new HandlerCommand(
            name: 'app:widen-pillars',
            description: 'Migrate identity_pillar to the two-axis (id, langcode) primary key for Anishinaabemowin peers. Preserves English history; idempotent; keeps a backup table.',
            handler: function (SymfonyCommandIO $io): int {
                $etm = $this->entityTypeManager();
                if ($etm === null) {
                    $io->error('Widening identity_pillar requires a booted kernel (EntityTypeManager).');

                    return 1;
                }

                return new WidenPillarsCommand($etm, $this->db())->run($io);
            },
        );

        yield new HandlerCommand(
            name: 'app:migrate-drive',
            description: 'Migrate Drive from the raw drive_file table to the entity-native drive_asset entity, verbatim. One-time and idempotent.',
            handler: function (SymfonyCommandIO $io): int {
                $etm = $this->entityTypeManager();
                if ($etm === null) {
                    $io->error('Drive migration requires a booted kernel (EntityTypeManager).');

                    return 1;
                }
                $command = new MigrateDriveCommand(new DriveFileService($etm), $this->db());

                return $command->run($io);
            },
        );

        yield new HandlerCommand(
            name: 'app:seed-ventures',
            description: 'Seed the Venture Numbers section (six lanes, gating facts, provenance snapshot) from the checked-in model mirror. Idempotent; never overwrites entered numbers.',
            handler: function (SymfonyCommandIO $io): int {
                $etm = $this->entityTypeManager();
                if ($etm === null) {
                    $io->error('Ventures seed requires a booted kernel (EntityTypeManager).');

                    return 1;
                }

                return new SeedVenturesCommand(new VentureService($etm))->run($io);
            },
        );

    }

    private function db(): DatabaseInterface
    {
        return $this->db ??= Db::persistent();
    }

    /**
     * Resolve the kernel EntityTypeManager for CLI handlers (entity-native
     * commands). Null when no kernel is present (routing-only unit tests).
     */
    private function entityTypeManager(): ?\Waaseyaa\Entity\EntityTypeManager
    {
        try {
            $resolved = $this->resolve(\Waaseyaa\Entity\EntityTypeManager::class);

            return $resolved instanceof \Waaseyaa\Entity\EntityTypeManager ? $resolved : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Storage root for Drive bytes. On the Pi this is the fnpi_storage volume
     * (WAASEYAA_FILES_DIR via config); default is the project storage dir.
     */
    private function filesDir(): string
    {
        $configured = $this->config['files_dir'] ?? '';

        return is_string($configured) && $configured !== ''
            ? $configured
            : $this->projectRoot . '/storage/files';
    }

    /**
     * @return list<string>
     */
    private function allowedUploadMimeTypes(): array
    {
        $configured = $this->config['upload_allowed_mime_types'] ?? null;
        $allowed = [];
        if (is_array($configured)) {
            foreach ($configured as $value) {
                if (is_string($value) && trim($value) !== '') {
                    $allowed[] = trim($value);
                }
            }
        }

        return $allowed !== [] ? $allowed : ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    }

    /**
     * Base URL of the Gotenberg conversion service. On the Pi this is the
     * compose service (http://gotenberg:3000); empty disables live conversion
     * (uploads are stored without a preview until reconverted).
     */
    private function gotenbergUrl(): string
    {
        $configured = $this->config['gotenberg_url'] ?? null;
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }
        $env = getenv('GOTENBERG_URL');

        return is_string($env) ? trim($env) : '';
    }

    /**
     * Whether Co-Intelligence may propose and apply workspace changes (agentic
     * mode). Off unless ANOKII_AGENT_TOOLS is 1/true/on, so the live chat stays
     * read-only grounded RAG until explicitly turned on.
     */
    private function agentToolsEnabled(): bool
    {
        $configured = $this->config['agent_tools'] ?? null;
        if (is_bool($configured)) {
            return $configured;
        }
        $value = strtolower(trim((string) (getenv('ANOKII_AGENT_TOOLS') ?: '')));

        return in_array($value, ['1', 'true', 'on', 'yes'], true);
    }

    private function uploadMaxBytes(): int
    {
        $configured = $this->config['upload_max_bytes'] ?? null;

        return is_numeric($configured) && (int) $configured > 0 ? (int) $configured : 10 * 1024 * 1024;
    }

    private function kernelPresent(): bool
    {
        try {
            return $this->resolve(DatabaseInterface::class) instanceof DatabaseInterface;
        } catch (\Throwable) {
            return false;
        }
    }
}
