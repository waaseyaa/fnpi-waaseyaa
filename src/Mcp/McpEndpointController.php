<?php

declare(strict_types=1);

namespace App\Mcp;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Mcp\McpEndpoint;

/**
 * HTTP adapter for the framework's MCP endpoint.
 *
 * On alpha.202 the package route points `Waaseyaa\Mcp\McpEndpoint::handle`,
 * which returns the mcp package's McpResponse value object — but the SSR
 * app-controller dispatcher only accepts a Symfony Response (or Inertia page),
 * so every /mcp request 500s ("returned an unsupported value"). Upstream gap;
 * see docs/waaseyaa-upstream-notes.md. McpAgentServiceProvider re-registers
 * the mcp.endpoint route onto this controller, which delegates to the real
 * (guarded, app-authenticated) McpEndpoint and converts the result.
 */
final readonly class McpEndpointController
{
    public function __construct(
        private McpEndpoint $endpoint,
    ) {}

    public function handle(AccountInterface $account, HttpRequest $request): HttpResponse
    {
        $mcpResponse = $this->endpoint->handle($account, $request);

        return new HttpResponse(
            $mcpResponse->body,
            $mcpResponse->statusCode,
            [
                'Content-Type' => $mcpResponse->contentType,
                'Cache-Control' => 'no-store',
            ],
        );
    }
}
