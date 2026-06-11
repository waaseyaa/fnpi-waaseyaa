<?php

declare(strict_types=1);

namespace App\Mcp;

use Waaseyaa\AI\Tools\AgentTool;
use Waaseyaa\AI\Tools\AgentToolInterface;
use Waaseyaa\AI\Tools\Attribute\AsAgentTool;
use Waaseyaa\AI\Tools\ToolNotFoundException;
use Waaseyaa\AI\Tools\ToolRegistryInterface;
use Waaseyaa\Entity\EntityTypeManagerInterface;

/**
 * Hand-constructed tool catalogue for the MCP surface, mirroring how
 * App\CoIntelligence\AgentTools builds the framework tools for the in-app
 * agent.
 *
 * Why not the framework AttributeToolRegistry: on alpha.202 nothing binds
 * PackageManifest (or ContainerInterface) onto the kernel-services bus, so
 * AiToolsServiceProvider hydrates the shared registry from an EMPTY fallback
 * manifest and /mcp would expose zero tools (upstream gap; see
 * docs/waaseyaa-upstream-notes.md).
 *
 * Tool metadata (name, capability, destructive, dry-run) is read from each
 * class's own #[AsAgentTool] attribute so it cannot drift from the framework.
 * The human-only revision-pointer tools (entity.set_current_revision,
 * entity.rollback) and the unconstructible/ungranted vector + relationship
 * tools are deliberately not in the catalogue.
 */
final class McpToolCatalogue implements ToolRegistryInterface
{
    /** @var list<class-string<AgentToolInterface>> */
    private const array TOOL_CLASSES = [
        \Waaseyaa\AI\Tools\Entity\EntityReadTool::class,
        \Waaseyaa\AI\Tools\Entity\EntityListTool::class,
        \Waaseyaa\AI\Tools\Entity\EntitySearchTool::class,
        \Waaseyaa\AI\Tools\Entity\EntityListRevisionsTool::class,
        \Waaseyaa\AI\Tools\Entity\EntityCreateTool::class,
        \Waaseyaa\AI\Tools\Entity\EntityUpdateTool::class,
        \Waaseyaa\AI\Tools\Entity\EntityDeleteTool::class,
        \Waaseyaa\AI\Agent\Tool\Bimaaji\IntrospectGraphTool::class,
        \Waaseyaa\AI\Agent\Tool\Bimaaji\IntrospectSectionTool::class,
        \Waaseyaa\AI\Agent\Tool\Bimaaji\SearchSpecsTool::class,
        \Waaseyaa\AI\Agent\Tool\Bimaaji\ProposeMutationTool::class,
        \Waaseyaa\AI\Agent\Tool\Bimaaji\GeneratePatchTool::class,
    ];

    /** @var array<string, AgentTool> */
    private array $tools = [];

    private bool $hydrated = false;

    /** @var \Closure(string): ?object */
    private readonly \Closure $resolver;

    /**
     * @param \Closure(string): ?object $resolver Resolves a constructor dependency
     *   by class name (provider bindings via the kernel-services bus); null skips
     *   the tool rather than failing the whole catalogue.
     */
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        \Closure $resolver,
    ) {
        $this->resolver = $resolver;
    }

    public function register(AgentTool $tool): void
    {
        $this->hydrate();
        $this->tools[$tool->name] = $tool;
    }

    public function get(string $name): AgentTool
    {
        $this->hydrate();
        if (!isset($this->tools[$name])) {
            throw ToolNotFoundException::forName($name);
        }

        return $this->tools[$name];
    }

    public function has(string $name): bool
    {
        $this->hydrate();

        return isset($this->tools[$name]);
    }

    public function all(): iterable
    {
        $this->hydrate();

        return array_values($this->tools);
    }

    private function hydrate(): void
    {
        if ($this->hydrated) {
            return;
        }
        $this->hydrated = true;

        foreach (self::TOOL_CLASSES as $class) {
            $attributes = new \ReflectionClass($class)->getAttributes(AsAgentTool::class);
            if ($attributes === []) {
                continue;
            }
            /** @var AsAgentTool $meta */
            $meta = $attributes[0]->newInstance();

            $impl = $this->instantiate($class);
            if ($impl === null) {
                continue;
            }

            $this->tools[$meta->name] = new AgentTool(
                name: $meta->name,
                capability: $meta->capability,
                destructive: $meta->destructive,
                dryRunSupported: $meta->dryRunSupported,
                category: $meta->category,
                inputSchema: $impl->inputSchema(),
                impl: $impl,
            );
        }
    }

    /**
     * @param class-string<AgentToolInterface> $class
     */
    private function instantiate(string $class): ?AgentToolInterface
    {
        $ref = new \ReflectionClass($class);
        $constructor = $ref->getConstructor();

        $args = [];
        foreach ($constructor?->getParameters() ?? [] as $param) {
            $type = $param->getType();
            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                return null;
            }
            $typeName = $type->getName();

            if (is_a($this->entityTypeManager, $typeName)) {
                $args[] = $this->entityTypeManager;
                continue;
            }

            $resolved = ($this->resolver)($typeName);
            if ($resolved === null) {
                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                    continue;
                }
                if ($param->allowsNull()) {
                    $args[] = null;
                    continue;
                }

                return null;
            }
            $args[] = $resolved;
        }

        try {
            $instance = $ref->newInstanceArgs($args);
        } catch (\Throwable) {
            return null;
        }

        return $instance instanceof AgentToolInterface ? $instance : null;
    }
}
