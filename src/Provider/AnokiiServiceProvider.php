<?php

declare(strict_types=1);

namespace App\Provider;

use App\Access\WorkspaceAccess;
use App\Auth\SetupTokenRepository;
use App\Auth\SetupTokenSchema;
use App\CLI\AnokiiInviteHandler;
use App\Command\AssignRoleCommand;
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
use App\Command\SeedDocumentsCommand;
use App\Command\SeedDriveCommand;
use App\Controller\AnokiiController;
use App\Controller\CoIntelligenceController;
use App\Controller\DocumentsController;
use App\Controller\DriveController;
use App\Controller\IdentityController;
use App\Documents\DocumentService;
use App\Documents\DocumentStorage;
use App\Documents\GotenbergClient;
use App\Drive\DriveFileService;
use App\Drive\DriveStorage;
use App\Identity\PillarService;
use App\Support\Db;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\AI\Agent\Provider\AnthropicProvider;
use Waaseyaa\AI\Agent\Provider\NullLlmProvider;
use Waaseyaa\AI\Agent\Provider\ProviderInterface;
use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Wires the authenticated Anokii workspace at /anokii/*: the shell (login,
 * logout, dashboard, settings, set-password) and tool #1 (Identity Workspace).
 *
 * Routes are registered ->allowAll() at the framework layer; each controller
 * enforces the session itself and redirects unauthenticated page requests to
 * /anokii/login (and returns 401 for JSON actions), so the gate's redirect
 * target is exactly /anokii/login. Public marketing routes live in
 * SiteServiceProvider and are untouched.
 */
final class AnokiiServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    /** Chat model, matching oiatc's Co-Intelligence (current Claude Sonnet). */
    private const CHAT_MODEL = 'claude-sonnet-4-6';

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

        $get = static fn(string $name, string $path, callable $c) => $router->addRoute(
            $name,
            RouteBuilder::create($path)->controller($c)->allowAll()->methods('GET')->build(),
        );
        $post = static fn(string $name, string $path, callable $c) => $router->addRoute(
            $name,
            RouteBuilder::create($path)->controller($c)->allowAll()->methods('POST')->build(),
        );

        $get('anokii.home', '/anokii', fn(Request $r) => $shell->dashboard($r));
        $get('anokii.login', '/anokii/login', fn(Request $r) => $shell->loginForm($r));
        $post('anokii.login.post', '/anokii/login', fn(Request $r) => $shell->loginSubmit($r));
        $get('anokii.logout', '/anokii/logout', fn(Request $r) => $shell->logout($r));
        $get('anokii.settings', '/anokii/settings', fn(Request $r) => $shell->settings($r));
        $post('anokii.settings.post', '/anokii/settings', fn(Request $r) => $shell->settingsSave($r));
        $get('anokii.setpw', '/anokii/set-password', fn(Request $r) => $shell->setPasswordForm($r));
        $post('anokii.setpw.post', '/anokii/set-password', fn(Request $r) => $shell->setPasswordSubmit($r));
        $get('anokii.identity', '/anokii/identity', fn(Request $r) => $identity->index($r));
        $post('anokii.identity.save', '/anokii/identity/save', fn(Request $r) => $identity->save($r));
        $get('anokii.identity.history', '/anokii/identity/{pid}/history', fn(Request $r, string $pid) => $identity->history($r, $pid));
        $get('anokii.cointelligence', '/anokii/cointelligence', fn(Request $r) => $cointel->index($r));
        $post('anokii.cointelligence.send', '/anokii/cointelligence/send', fn(Request $r) => $cointel->send($r));
        $post('anokii.cointelligence.apply', '/anokii/cointelligence/apply', fn(Request $r) => $cointel->apply($r));
        $get('anokii.drive', '/anokii/drive', fn(Request $r) => $drive->index($r));
        $post('anokii.drive.upload', '/anokii/drive/upload', fn(Request $r) => $drive->upload($r));
        $get('anokii.drive.file', '/anokii/drive/file/{id}', fn(Request $r, string $id) => $drive->download($r, $id));
        $post('anokii.drive.delete', '/anokii/drive/delete', fn(Request $r) => $drive->delete($r));
        $get('anokii.documents', '/anokii/documents', fn(Request $r) => $documents->index($r));
        $post('anokii.documents.create', '/anokii/documents/create', fn(Request $r) => $documents->create($r));
        $get('anokii.documents.show', '/anokii/documents/{uuid}', fn(Request $r, string $uuid) => $documents->show($r, $uuid));
        $post('anokii.documents.version', '/anokii/documents/{uuid}/version', fn(Request $r, string $uuid) => $documents->uploadVersion($r, $uuid));
        $post('anokii.documents.setcurrent', '/anokii/documents/{uuid}/set-current', fn(Request $r, string $uuid) => $documents->setCurrent($r, $uuid));
        $post('anokii.documents.rollback', '/anokii/documents/{uuid}/rollback', fn(Request $r, string $uuid) => $documents->rollback($r, $uuid));
        $post('anokii.documents.note', '/anokii/documents/{uuid}/note', fn(Request $r, string $uuid) => $documents->addNote($r, $uuid));
        $get('anokii.documents.file', '/anokii/documents/{uuid}/file/{vid}/{kind}', fn(Request $r, string $uuid, string $vid, string $kind) => $documents->download($r, $uuid, $vid, $kind));
        // Coming-soon placeholder for not-yet-live modules (rooms, ...).
        $get('anokii.module', '/anokii/m/{module}', fn(Request $r, string $module) => $shell->comingSoon($r, $module));
    }

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'anokii:invite',
            description: 'Create (if needed) an Anokii account and print a one-time set-password link',
            arguments: [
                new ArgumentDefinition(
                    name: 'email',
                    mode: ArgumentMode::Required,
                    description: 'Email address of the account to invite',
                ),
            ],
            options: [
                new OptionDefinition(name: 'name', mode: OptionMode::Required, description: 'Display name for a new account'),
                new OptionDefinition(name: 'base-url', mode: OptionMode::Required, description: 'Base URL for the link (default https://fnprocure.ca)'),
            ],
            handler: [AnokiiInviteHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'app:ingest-knowledge',
            description: 'Build the Co-Intelligence RAG knowledge base from FNPI docs, the live site copy, and the Identity pillars.',
            options: [
                new OptionDefinition(name: 'dry-run', mode: OptionMode::None, description: 'Preview extracted chunks without writing.'),
                new OptionDefinition(name: 'prune', mode: OptionMode::Negatable, description: 'Delete stored chunks no longer present (use --no-prune to keep).', default: true),
            ],
            handler: function (CliIO $io): int {
                $db = $this->db();
                new ChatSchema($db)->ensure();
                $twig = SsrServiceProvider::createTwigEnvironment($this->projectRoot, $this->config);
                $command = new IngestKnowledgeCommand(
                    new DocChunkRepository($db),
                    new PillarService($this->entityTypeManager()),
                    $twig,
                    $this->projectRoot . '/resources/knowledge',
                );

                return $command->run($io);
            },
        );

        yield new CommandDefinition(
            name: 'app:seed-drive',
            description: 'Seed Drive with a directory of images. Bytes go to the sovereign volume via the media layer; one index row per file. Idempotent.',
            options: [
                new OptionDefinition(name: 'dir', mode: OptionMode::Required, description: 'Directory of images to import (required).'),
                new OptionDefinition(name: 'folder', mode: OptionMode::Required, description: 'Target folder / tag (default "Global relationships").'),
                new OptionDefinition(name: 'owner-email', mode: OptionMode::Required, description: 'Attribute uploads to this account when it exists.'),
                new OptionDefinition(name: 'owner-id', mode: OptionMode::Required, description: 'Fallback owner uid (default 1).'),
                new OptionDefinition(name: 'owner-label', mode: OptionMode::Required, description: 'Fallback display name (default "Matthew Owl").'),
            ],
            handler: function (CliIO $io): int {
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

        yield new CommandDefinition(
            name: 'app:seed-documents',
            description: 'Seed the CANCOM document (3 versions + opening note) into the entity-native Documents tool. Idempotent.',
            options: [
                new OptionDefinition(name: 'matthew-email', mode: OptionMode::Required, description: 'Account to attribute Matthew\'s versions to (default matthew@fnprocure.ca).'),
                new OptionDefinition(name: 'russell-email', mode: OptionMode::Required, description: 'Account to attribute Russell\'s version + note to (default russell@fnprocure.ca).'),
            ],
            handler: function (CliIO $io): int {
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

        yield new CommandDefinition(
            name: 'app:migrate-pillars',
            description: 'Migrate the Identity Workspace from the raw pillar table to the entity-native identity_pillar entity, verbatim. One-time and idempotent.',
            handler: function (CliIO $io): int {
                $etm = $this->entityTypeManager();
                if ($etm === null) {
                    $io->error('Pillar migration requires a booted kernel (EntityTypeManager).');

                    return 1;
                }
                $command = new MigratePillarsCommand(new PillarService($etm), $this->db());

                return $command->run($io);
            },
        );

        yield new CommandDefinition(
            name: 'app:migrate-drive',
            description: 'Migrate Drive from the raw drive_file table to the entity-native drive_asset entity, verbatim. One-time and idempotent.',
            handler: function (CliIO $io): int {
                $etm = $this->entityTypeManager();
                if ($etm === null) {
                    $io->error('Drive migration requires a booted kernel (EntityTypeManager).');

                    return 1;
                }
                $command = new MigrateDriveCommand(new DriveFileService($etm), $this->db());

                return $command->run($io);
            },
        );

        yield new CommandDefinition(
            name: 'app:assign-role',
            description: 'Assign an Anokii workspace role (admin | editor | viewer) to an account, by email or numeric uid.',
            arguments: [
                new ArgumentDefinition(name: 'role', mode: ArgumentMode::Required, description: 'admin, editor, or viewer'),
                new ArgumentDefinition(name: 'user', mode: ArgumentMode::Required, description: 'Account email or numeric uid'),
            ],
            handler: fn(CliIO $io): int => new AssignRoleCommand($this->entityTypeManager())->run($io),
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
