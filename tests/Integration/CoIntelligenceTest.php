<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\CoIntelligence\ChatPromptBuilder;
use App\CoIntelligence\ChatSchema;
use App\CoIntelligence\ChunkData;
use App\CoIntelligence\ConversationRepository;
use App\CoIntelligence\KnowledgeChunker;
use App\Controller\CoIntelligenceController;
use App\Provider\AnokiiServiceProvider;
use App\Support\AnokiiShell;
use Anokii\CoIntelligence\GraphRetriever;
use Anokii\CoIntelligence\Passage;
use Anokii\CoIntelligence\PrefixScorer;
use Anokii\CoIntelligence\TopicVocabulary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\AI\Agent\Provider\NullLlmProvider;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Co-Intelligence: routing + auth gating, the keyword retriever, conversation
 * persistence with per-user attribution, the prompt contract, and the chat
 * template rendering inside the Anokii shell.
 */
final class CoIntelligenceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $provider = new SsrServiceProvider();
        $provider->setKernelContext(dirname(__DIR__, 2), [], []);
        $provider->boot();

        // Make the shared package templates (and the @anokiipkg namespace
        // _fnpi_base extends) resolvable, exactly as the runtime provider does.
        \App\Tests\Support\ShellTemplates::register();
    }

    private function db(): DatabaseInterface
    {
        $db = DBALDatabase::createSqlite(':memory:');
        new ChatSchema($db)->ensure();

        return $db;
    }

    #[Test]
    public function cointelligence_routes_are_registered(): void
    {
        $router = new WaaseyaaRouter();
        new AnokiiServiceProvider()->routes($router);

        $this->assertSame('anokii.cointelligence', $router->match('/admin/anokii/cointelligence')['_route'] ?? null);
        $this->assertSame('anokii.cointelligence.messages', $router->match('/admin/anokii/cointelligence/7/messages')['_route'] ?? null);
    }

    #[Test]
    public function messages_is_401_when_signed_out(): void
    {
        $controller = $this->controller(false);
        $response = $controller->messages(new Request(), '7');

        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function index_redirects_to_login_when_signed_out(): void
    {
        $controller = $this->controller(false);
        $response = $controller->index(new Request());

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/admin/anokii/login', $response->getTargetUrl());
    }

    #[Test]
    public function send_is_401_when_signed_out(): void
    {
        $controller = $this->controller(false);
        $request = Request::create('/admin/anokii/cointelligence/send', 'POST', [], [], [], [], (string) json_encode(['question' => 'What are the lanes?']));
        $response = $controller->send($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function a_conversation_persists_with_per_user_attribution(): void
    {
        $repo = new ConversationRepository($this->db());

        $cid = $repo->create('Lanes question', 'Russell');
        $this->assertGreaterThan(0, $cid);

        $repo->addMessage($cid, 'user', 'Russell', 'What are the three lanes?');
        $repo->addMessage($cid, 'assistant', 'Co-Intelligence', 'FNPI has three lanes.', [
            ['title' => 'FNPI Tech Lane Summary', 'source_url' => 'knowledge/tech-lane-summary'],
        ]);

        $messages = $repo->messages($cid);
        $this->assertCount(2, $messages);
        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame('Russell', $messages[0]['author']);
        $this->assertSame('assistant', $messages[1]['role']);
        $this->assertSame('FNPI Tech Lane Summary', $messages[1]['sources'][0]['title']);

        $recent = $repo->recent();
        $this->assertNotEmpty($recent);
        $this->assertSame('Lanes question', $recent[0]['title']);
    }

    #[Test]
    public function markdown_chunker_splits_on_headings(): void
    {
        $chunks = new KnowledgeChunker()->chunkMarkdown(
            "# Title\nIntro text that is long enough to be kept as a chunk.\n\n## Section A\nThe body of section A with plenty of characters to pass the minimum.",
            'knowledge/test',
            'Test Doc',
        );

        $this->assertGreaterThanOrEqual(2, count($chunks));
        $headings = array_map(static fn(ChunkData $c): string => $c->heading, $chunks);
        $this->assertContains('Section A', $headings);
        $this->assertSame('Test Doc', $chunks[0]->title);
    }

    #[Test]
    public function prompt_builder_grounds_cites_and_strips_dashes(): void
    {
        $pb = new ChatPromptBuilder();

        $system = $pb->system();
        $this->assertStringContainsString('ONLY from the passages', $system);
        $this->assertStringContainsString('(source: <title>)', $system);

        $userMessage = $pb->userMessage('What is the positioning?', [
            new Passage('knowledge/fnpi-narrative', 'FNPI Narrative', 'Positioning', 'One qualification, three lanes.', 2.0),
        ]);
        $this->assertStringContainsString('[Passage 1]', $userMessage);
        $this->assertStringContainsString('FNPI Narrative', $userMessage);
        $this->assertStringContainsString('One qualification, three lanes.', $userMessage);

        // Em dash collapses to a comma; en dash becomes a hyphen.
        $this->assertSame('a, b', ChatPromptBuilder::sanitizeDashes("a\u{2014}b"));
        $this->assertSame('9-5', ChatPromptBuilder::sanitizeDashes("9\u{2013}5"));
    }

    #[Test]
    public function chat_template_renders_inside_the_shell(): void
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        $this->assertNotNull($twig);

        $html = $twig->render('anokii/cointelligence.html.twig', $this->shell('ai') + [
            'suggested' => ["Summarize FNPI's three lanes."],
            'recent' => [],
            'active' => null,
            'messages' => [],
            'configured' => true,
        ]);

        $this->assertStringContainsString('Co-Intelligence', $html);
        $this->assertStringContainsString('Suggested prompts', $html);
        $this->assertStringContainsString('chat-stream', $html);
        // Rendered inside the shell (sidebar + topbar).
        $this->assertStringContainsString('Sovereign workspace', $html);
        $this->assertStringContainsString('class="aavatar"', $html);
        $this->assertStringNotContainsString('{%', $html);
    }

    #[Test]
    public function cointelligence_module_is_live(): void
    {
        $ai = AnokiiShell::find('ai');
        $this->assertNotNull($ai);
        $this->assertTrue($ai['live']);
        $this->assertSame('/admin/anokii/cointelligence', $ai['href']);
        $this->assertSame('', $ai['badge']);
    }

    private function controller(bool $configured): CoIntelligenceController
    {
        $db = $this->db();

        // Retrieval is the shared package engine in FNPI's flat, word-prefix mode
        // (the signed-out tests below never call it; it is only constructed).
        $retriever = new GraphRetriever($db, new TopicVocabulary(), 0.45, 0.0, true, new PrefixScorer());

        return new CoIntelligenceController(
            null,
            $retriever,
            new ChatPromptBuilder(),
            new ConversationRepository($db),
            new NullLlmProvider(),
            $configured,
        );
    }

    /** Minimal shell context for template render tests. */
    private function shell(string $active): array
    {
        return [
            'nav_active' => $active,
            'modules' => AnokiiShell::modules(),
            'user_label' => 'Russell',
            'user_role' => 'Editor',
            'user_initials' => 'RU',
        ];
    }
}
