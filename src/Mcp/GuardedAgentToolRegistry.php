<?php

declare(strict_types=1);

namespace App\Mcp;

use Waaseyaa\AI\Tools\AgentTool;
use Waaseyaa\AI\Tools\ToolRegistryInterface;

/**
 * The MCP endpoint's view of the framework tool registry: every tool comes
 * back wrapped in {@see GuardedAgentTool} (scope guard + audit + dry_run
 * transport support), and the human-only revision-pointer tools are hidden
 * from enumeration.
 *
 * get() still resolves hidden tools — deliberately — so a caller invoking one
 * by name receives the explicit "human-only" denial from the guard rather
 * than an opaque unknown-tool error, and the deny holds even if enumeration
 * filtering were bypassed.
 *
 * Only the request-scoped McpEndpoint constructed by McpAgentServiceProvider
 * sees this registry; the shared AttributeToolRegistry and the in-app
 * Co-Intelligence tool layer are untouched.
 */
final class GuardedAgentToolRegistry implements ToolRegistryInterface
{
    /** @var array<string, AgentTool> */
    private array $wrapped = [];

    public function __construct(
        private readonly ToolRegistryInterface $inner,
        private readonly McpInvocationAuditor $auditor,
    ) {}

    public function register(AgentTool $tool): void
    {
        $this->inner->register($tool);
    }

    public function get(string $name): AgentTool
    {
        return $this->wrap($this->inner->get($name));
    }

    public function has(string $name): bool
    {
        return $this->inner->has($name);
    }

    public function all(): iterable
    {
        foreach ($this->inner->all() as $tool) {
            if (\in_array($tool->name, McpAgentScope::DENIED_TOOLS, true)) {
                continue;
            }
            yield $this->wrap($tool);
        }
    }

    private function wrap(AgentTool $tool): AgentTool
    {
        if (isset($this->wrapped[$tool->name])) {
            return $this->wrapped[$tool->name];
        }

        $guarded = new GuardedAgentTool(
            name: $tool->name,
            inner: $tool->impl,
            dryRunSupported: $tool->dryRunSupported,
            auditor: $this->auditor,
        );

        return $this->wrapped[$tool->name] = new AgentTool(
            name: $tool->name,
            capability: $tool->capability,
            destructive: $tool->destructive,
            dryRunSupported: $tool->dryRunSupported,
            category: $tool->category,
            inputSchema: $guarded->inputSchema(),
            impl: $guarded,
        );
    }
}
