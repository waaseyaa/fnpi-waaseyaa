<?php

declare(strict_types=1);

namespace App\Provider;

use App\CLI\McpAgentAccountHandler;
use App\Mcp\GuardedAgentToolRegistry;
use App\Mcp\McpEndpointController;
use App\Mcp\McpInvocationAuditor;
use App\Mcp\McpRecentInvocationsQuery;
use App\Mcp\McpToolCatalogue;
use Waaseyaa\AI\Tools\ToolRegistryInterface;
use Waaseyaa\Audit\Contract\AuditQueryInterface;
use Waaseyaa\Audit\Contract\AuditWriterInterface;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Mcp\Admin\RecentInvocationsQueryInterface;
use Waaseyaa\Mcp\Auth\BearerTokenAuth;
use Waaseyaa\Mcp\Auth\McpAuthInterface;
use Waaseyaa\Mcp\McpEndpoint;
use Waaseyaa\User\User;

/**
 * Wires the framework's MCP endpoint (POST /mcp, waaseyaa/mcp) for the FNPI
 * draft agent.
 *
 * The framework default is BearerTokenAuth(tokens: []) — nothing can connect.
 * Re-binding McpAuthInterface app-side is NOT enough on alpha.202: provider
 * resolution is first-match in registration order and the app's providers
 * register after Waaseyaa\Mcp\McpServiceProvider, so the framework's empty
 * default would always win. Nothing binds McpEndpoint::class though, so this
 * provider binds the endpoint itself (SsrPageHandler::resolveControllerInstance
 * checks the service resolver before reflection) and hands it:
 *
 *  - BearerTokenAuth mapping the WAASEYAA_MCP_AGENT_TOKEN env secret to the
 *    persisted mcp-agent service account (see McpAgentAccountHandler). Token
 *    unset or account missing → empty map → every request 401s (fail closed).
 *  - GuardedAgentToolRegistry over the framework AttributeToolRegistry:
 *    workspace entity-type scope, publish/revision field denials, human-only
 *    revision-pointer tools, transport dry_run support, and per-invocation
 *    OCAP audit logging.
 */
final class McpAgentServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public const string TOKEN_ENV = 'WAASEYAA_MCP_AGENT_TOKEN';

    public function register(): void
    {
        $this->singleton(McpEndpoint::class, function (): McpEndpoint {
            return new McpEndpoint(
                auth: $this->buildAuth(),
                agentRegistry: new GuardedAgentToolRegistry(
                    inner: $this->resolveAgentRegistry(),
                    auditor: $this->buildAuditor(),
                ),
            );
        });

        // Same auth for anything else resolving McpAuthInterface. On alpha.202
        // the framework's own (empty) binding still wins first-match for the
        // admin server-config read model — cosmetic only; the endpoint above
        // never resolves the interface from the container.
        $this->singleton(McpAuthInterface::class, fn(): McpAuthInterface => $this->buildAuth());

        // Backs the admin tool-detail recent-invocations table from the audit
        // log; the framework's optional ai-observability adapter for this port
        // does not exist in alpha.202.
        $this->singleton(RecentInvocationsQueryInterface::class, function (): RecentInvocationsQueryInterface {
            $query = $this->resolve(AuditQueryInterface::class);
            \assert($query instanceof AuditQueryInterface);

            return new McpRecentInvocationsQuery($query);
        });

        $this->singleton(McpEndpointController::class, function (): McpEndpointController {
            $endpoint = $this->resolve(McpEndpoint::class);
            \assert($endpoint instanceof McpEndpoint);

            return new McpEndpointController($endpoint);
        });
    }

    public function routes(\Waaseyaa\Routing\WaaseyaaRouter $router, \Waaseyaa\Entity\EntityTypeManager $entityTypeManager): void
    {
        // alpha.202 upstream gap: the package route targets McpEndpoint::handle,
        // whose McpResponse return the SSR app-controller dispatcher cannot
        // convert — every /mcp request 500s. Override the route (app providers
        // register after Waaseyaa\Mcp\McpServiceProvider; removeRoute() is the
        // documented override lever) onto the converting app controller.
        $router->removeRoute('mcp.endpoint');
        $router->addRoute(
            'mcp.endpoint',
            \Waaseyaa\Routing\RouteBuilder::create('/mcp')
                ->controller(McpEndpointController::class . '::handle')
                ->methods('POST', 'GET')
                ->csrfExempt()
                ->build(),
        );
    }

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'mcp:agent-account',
            description: 'Ensure the MCP agent service account exists with exactly the agreed capability set',
            handler: [McpAgentAccountHandler::class, 'execute'],
        );
    }

    private function buildAuth(): McpAuthInterface
    {
        $token = (string) (getenv(self::TOKEN_ENV) ?: '');
        if ($token === '') {
            return new BearerTokenAuth(tokens: []);
        }

        $account = $this->loadAgentAccount();
        if ($account === null) {
            return new BearerTokenAuth(tokens: []);
        }

        return new BearerTokenAuth(tokens: [$token => $account]);
    }

    private function loadAgentAccount(): ?User
    {
        try {
            $entityTypeManager = $this->resolve(EntityTypeManagerInterface::class);
            \assert($entityTypeManager instanceof EntityTypeManagerInterface);
            $user = $entityTypeManager->getStorage('user')->loadByKey('mail', McpAgentAccountHandler::AGENT_MAIL);
        } catch (\Throwable) {
            return null;
        }

        if (!$user instanceof User || !$user->isActive()) {
            return null;
        }

        // Defense in depth: the token only ever authenticates the dedicated
        // service account. If the stored account ever gained the administrator
        // role (all permissions), refuse it rather than honour the escalation.
        if (\in_array('administrator', $user->getRoles(), true)) {
            return null;
        }

        return $user;
    }

    private function resolveAgentRegistry(): ToolRegistryInterface
    {
        // Hand-built catalogue, not the framework AttributeToolRegistry: on
        // alpha.202 nothing serves PackageManifest on the kernel bus, so the
        // shared registry hydrates from an empty manifest and exposes zero
        // tools (upstream gap; see McpToolCatalogue).
        $entityTypeManager = $this->resolve(EntityTypeManagerInterface::class);
        \assert($entityTypeManager instanceof EntityTypeManagerInterface);

        return new McpToolCatalogue(
            entityTypeManager: $entityTypeManager,
            resolver: fn(string $class): ?object => $this->resolveOptional($class),
        );
    }

    private function buildAuditor(): McpInvocationAuditor
    {
        $writer = $this->resolveOptional(AuditWriterInterface::class);

        return new McpInvocationAuditor($writer instanceof AuditWriterInterface ? $writer : null);
    }
}
