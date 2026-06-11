<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp;

use App\Mcp\GuardedAgentTool;
use App\Mcp\McpInvocationAuditor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\AI\Tools\AgentToolInterface;
use Waaseyaa\AI\Tools\AgentToolResult;

final class GuardedAgentToolTest extends TestCase
{
    /** @var list<string> calls observed on the inner tool */
    private array $calls = [];

    #[Test]
    public function dry_run_argument_routes_to_dry_run_and_is_stripped(): void
    {
        $tool = $this->guarded('entity.create');
        $result = $tool->execute(
            ['entity_type' => 'page', 'values' => ['title' => 'x'], 'dry_run' => true],
            $this->account(),
        );

        $this->assertFalse($result->isError);
        $this->assertSame(['dryRun'], $this->calls);
    }

    #[Test]
    public function execute_denies_before_reaching_the_inner_tool(): void
    {
        $tool = $this->guarded('entity.update');
        $result = $tool->execute(
            ['entity_type' => 'page', 'id' => 1, 'values' => ['published_revision_id' => 9]],
            $this->account(),
        );

        $this->assertTrue($result->isError);
        $this->assertSame('publish_denied', $result->summary);
        $this->assertSame([], $this->calls, 'inner tool must not run on a denial');
    }

    #[Test]
    public function input_schema_advertises_dry_run(): void
    {
        $schema = $this->guarded('entity.create')->inputSchema();

        $this->assertArrayHasKey('dry_run', $schema['properties']);
    }

    private function guarded(string $name): GuardedAgentTool
    {
        $calls = &$this->calls;
        $inner = new class ($calls) implements AgentToolInterface {
            /** @param list<string> $calls */
            public function __construct(private array &$calls) {}

            public function execute(array $arguments, AccountInterface $account): AgentToolResult
            {
                $this->calls[] = 'execute';
                \assert(!\array_key_exists('dry_run', $arguments), 'dry_run must be stripped');

                return AgentToolResult::success([['type' => 'json', 'data' => ['ok' => true]]]);
            }

            public function dryRun(array $arguments, AccountInterface $account): AgentToolResult
            {
                $this->calls[] = 'dryRun';

                return AgentToolResult::success([['type' => 'json', 'data' => ['would' => true]]]);
            }

            public function argumentsForAudit(array $arguments): array
            {
                return $arguments;
            }

            public function inputSchema(): array
            {
                return ['type' => 'object', 'properties' => ['entity_type' => ['type' => 'string']]];
            }

            public function description(): string
            {
                return 'stub';
            }
        };

        return new GuardedAgentTool(
            name: $name,
            inner: $inner,
            dryRunSupported: true,
            auditor: new McpInvocationAuditor(null),
        );
    }

    private function account(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int
            {
                return 5;
            }

            public function hasPermission(string $permission): bool
            {
                return true;
            }

            public function getRoles(): array
            {
                return [];
            }

            public function isAuthenticated(): bool
            {
                return true;
            }
        };
    }
}
