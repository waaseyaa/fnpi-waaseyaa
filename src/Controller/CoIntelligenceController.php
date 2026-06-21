<?php

declare(strict_types=1);

namespace App\Controller;

use App\CoIntelligence\AgentConversation;
use App\CoIntelligence\AgentProposalRepository;
use App\CoIntelligence\ChatPromptBuilder;
use App\CoIntelligence\ConversationRepository;
use App\CoIntelligence\Passage;
use App\CoIntelligence\Retriever;
use App\Support\AnokiiShell;
use Anokii\Support\Auth;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Waaseyaa\AI\Agent\Provider\MessageRequest;
use Waaseyaa\AI\Agent\Provider\ProviderInterface;
use Waaseyaa\AI\Agent\Provider\StreamChunk;
use Waaseyaa\AI\Agent\Provider\StreamingProviderInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Tool #2: Co-Intelligence, a grounded, cited RAG chat over FNPI's own
 * knowledge base, mirroring oiatc's chat. Authenticated (only the FNPI accounts
 * reach it), single-turn grounded answers streamed back as SSE, and every turn
 * persisted to a shared conversation with per-user attribution.
 */
final class CoIntelligenceController
{
    private const TOP_K = 6;
    private const MAX_QUESTION_CHARS = 500;
    private const MAX_TOKENS = 900;

    /** A few FNPI starter prompts shown when a thread is empty. */
    private const SUGGESTED = [
        "Summarize FNPI's three lanes.",
        'What is our positioning?',
        'Explain the tech-lane business model.',
        'What is the vendor qualification pathway?',
    ];

    public function __construct(
        private readonly ?EntityTypeManager $entityTypeManager,
        private readonly Retriever $retriever,
        private readonly ChatPromptBuilder $prompts,
        private readonly ConversationRepository $conversations,
        private readonly ProviderInterface $provider,
        private readonly bool $configured,
        private readonly ?AgentConversation $agent = null,
        private readonly ?AgentProposalRepository $proposals = null,
        private readonly bool $agentEnabled = false,
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new RedirectResponse('/admin/anokii/login');
        }

        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            return new Response('Anokii unavailable: Twig is not initialised.', 500);
        }

        // Optional ?c=<id> selects an existing thread to render its history.
        $activeId = (int) $request->query->get('c', '0');
        $active = $activeId > 0 ? $this->conversations->find($activeId) : null;
        $messages = $active !== null ? $this->conversations->messages($activeId) : [];

        $context = AnokiiShell::context($user, 'ai') + [
            'suggested' => self::SUGGESTED,
            'recent' => $this->conversations->recent(20),
            'active' => $active,
            'messages' => $messages,
            'configured' => $this->configured,
        ];

        return new Response(
            $twig->render('anokii/cointelligence.html.twig', $context),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    public function send(Request $request): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Not signed in.'], 401);
        }

        $payload = json_decode((string) $request->getContent(), true);
        $data = is_array($payload) ? $payload : [];

        $question = trim((string) ($data['question'] ?? ''));
        if ($question === '' || mb_strlen($question) > self::MAX_QUESTION_CHARS) {
            return new JsonResponse(['ok' => false, 'error' => 'Provide a non-empty question (max 500 characters).'], 422);
        }

        $author = Auth::label($user);

        // Resolve or open the shared conversation, then record the user's turn.
        $conversationId = (int) ($data['conversation_id'] ?? 0);
        if ($conversationId <= 0 || !$this->conversations->exists($conversationId)) {
            $conversationId = $this->conversations->create($this->titleFrom($question), $author);
        }
        $this->conversations->addMessage($conversationId, 'user', $author, $question);

        $conversation = $this->conversations->find($conversationId);
        $title = $conversation['title'] ?? $this->titleFrom($question);

        if (!$this->configured) {
            $message = 'Co-Intelligence is not configured on this instance yet. Once the model key is set, answers will be grounded in FNPI\'s knowledge base.';
            $this->conversations->addMessage($conversationId, 'assistant', 'Co-Intelligence', $message);

            return $this->streamFixed($conversationId, $title, $message);
        }

        $passages = $this->retriever->retrieve($question, self::TOP_K);

        // Agentic mode (gated by flag): Co-Intelligence can both answer and
        // propose changes to the workspace. Off by default, so the live chat is
        // the unchanged read-only grounded RAG until enabled.
        if ($this->agentEnabled && $this->agent !== null) {
            return $this->streamAgent($conversationId, $title, $question, $passages, $user);
        }

        return $this->streamAnswer($conversationId, $title, $question, $passages);
    }

    /**
     * Return a conversation's messages as JSON so a chat surface can rehydrate
     * its history (the Identity companion persists its thread id client-side and
     * loads it on each page render, which matters because applying an agent
     * change reloads the page). Only user/assistant turns, content + author.
     */
    public function messages(Request $request, string $id): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Not signed in.'], 401);
        }

        $conversationId = (int) $id;
        $conversation = $conversationId > 0 ? $this->conversations->find($conversationId) : null;
        if ($conversation === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Unknown conversation.'], 404);
        }

        $out = [];
        foreach ($this->conversations->messages($conversationId) as $message) {
            if (!in_array($message['role'], ['user', 'assistant'], true)) {
                continue;
            }
            $out[] = [
                'role' => $message['role'],
                'author' => $message['author'],
                'content' => $message['content'],
            ];
        }

        return new JsonResponse([
            'ok' => true,
            'id' => $conversationId,
            'title' => $conversation['title'],
            'messages' => $out,
        ]);
    }

    /**
     * Approve or reject a pending agent proposal. On approve the change executes
     * as the signed-in user (gated by the same AccessPolicy as the UI), records a
     * revision, and the loop resumes; on reject the model is told and resumes.
     */
    public function apply(Request $request): Response
    {
        $user = Auth::currentUser($this->entityTypeManager);
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Not signed in.'], 401);
        }
        if (!$this->agentEnabled || $this->agent === null || $this->proposals === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Agent actions are not enabled.'], 403);
        }

        $payload = json_decode((string) $request->getContent(), true);
        $data = is_array($payload) ? $payload : [];
        $token = trim((string) ($data['token'] ?? ''));
        $approve = (string) ($data['decision'] ?? '') === 'approve';

        $proposal = $this->proposals->find($token);
        if ($proposal === null || (string) ($proposal['status'] ?? '') !== 'pending') {
            return new JsonResponse(['ok' => false, 'error' => 'Unknown or already-resolved proposal.'], 404);
        }
        // Only the account that was proposed to may act on it.
        if ((int) ($proposal['author_uid'] ?? 0) !== $user->id()) {
            return new JsonResponse(['ok' => false, 'error' => 'This proposal belongs to another account.'], 403);
        }

        $this->proposals->markStatus($token, $approve ? 'applied' : 'rejected');

        $agent = $this->agent;
        $account = $user;
        $label = Auth::label($user);

        return $this->sse(static function () use ($agent, $proposal, $approve, $account, $label): void {
            self::emit('meta', ['conversation_id' => (int) ($proposal['conversation_id'] ?? 0)]);
            $agent->applyDecision($proposal, $approve, $account, $label, static function (string $event, array $payload): void {
                self::emit($event, $payload);
            });
        });
    }

    /**
     * @param list<Passage> $passages
     */
    private function streamAgent(int $conversationId, string $title, string $question, array $passages, \Waaseyaa\User\User $user): StreamedResponse
    {
        $agent = $this->agent;
        $userMessage = $this->prompts->userMessage($question, $passages);
        $account = $user;
        $label = Auth::label($user);

        return $this->sse(static function () use ($agent, $conversationId, $title, $userMessage, $account, $label): void {
            self::emit('meta', ['conversation_id' => $conversationId, 'title' => $title]);
            if ($agent === null) {
                return;
            }
            $agent->respond($conversationId, $userMessage, $account, $label, static function (string $event, array $payload): void {
                self::emit($event, $payload);
            });
        });
    }

    /**
     * @param list<Passage> $passages
     */
    private function streamAnswer(int $conversationId, string $title, string $question, array $passages): StreamedResponse
    {
        $messageRequest = new MessageRequest(
            messages: [['role' => 'user', 'content' => $this->prompts->userMessage($question, $passages)]],
            system: $this->prompts->system(),
            tools: [],
            maxTokens: self::MAX_TOKENS,
        );
        $sources = $this->sources($passages);
        $provider = $this->provider;
        $prompts = $this->prompts;
        $conversations = $this->conversations;
        $noAnswer = $this->prompts->noAnswer();

        return $this->sse(static function () use ($provider, $messageRequest, $sources, $prompts, $conversations, $conversationId, $title, $noAnswer): void {
            self::emit('meta', ['conversation_id' => $conversationId, 'title' => $title]);

            $answer = '';
            try {
                if ($provider instanceof StreamingProviderInterface) {
                    $provider->streamMessage($messageRequest, static function (StreamChunk $chunk) use (&$answer): void {
                        if ($chunk->type === 'text_delta' && $chunk->text !== '') {
                            $clean = ChatPromptBuilder::sanitizeDashes($chunk->text);
                            $answer .= $clean;
                            self::emit('delta', ['text' => $clean]);
                        }
                    });
                } else {
                    $response = $provider->sendMessage($messageRequest);
                    $answer = ChatPromptBuilder::sanitizeDashes($response->getText());
                    self::emit('delta', ['text' => $answer]);
                }

                // If the model refused, no sources are worth showing.
                $emitSources = trim($answer) === trim($noAnswer) ? [] : $sources;
                $conversations->addMessage($conversationId, 'assistant', 'Co-Intelligence', $answer, $emitSources);
                self::emit('done', ['sources' => $emitSources]);
            } catch (\Throwable $e) {
                $fallback = $prompts->noAnswer();
                $conversations->addMessage($conversationId, 'assistant', 'Co-Intelligence', $fallback);
                self::emit('delta', ['text' => $fallback]);
                self::emit('done', ['sources' => []]);
            }
        });
    }

    private function streamFixed(int $conversationId, string $title, string $message): StreamedResponse
    {
        return $this->sse(static function () use ($conversationId, $title, $message): void {
            self::emit('meta', ['conversation_id' => $conversationId, 'title' => $title]);
            self::emit('delta', ['text' => $message]);
            self::emit('done', ['sources' => []]);
        });
    }

    private function sse(callable $body): StreamedResponse
    {
        return new StreamedResponse(static function () use ($body): void {
            $body();
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function emit(string $event, array $data): void
    {
        echo 'event: ' . $event . "\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
    }

    /**
     * @param list<Passage> $passages
     *
     * @return list<array{title: string, source_url: string}>
     */
    private function sources(array $passages): array
    {
        $seen = [];
        $out = [];
        foreach ($passages as $p) {
            if (isset($seen[$p->title])) {
                continue;
            }
            $seen[$p->title] = true;
            $out[] = ['title' => $p->title, 'source_url' => $p->sourceUrl];
        }

        return $out;
    }

    private function titleFrom(string $question): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $question) ?? $question);

        return mb_strlen($title) > 60 ? mb_substr($title, 0, 57) . '...' : $title;
    }
}
