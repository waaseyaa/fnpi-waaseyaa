<?php

declare(strict_types=1);

namespace App\Command;

use App\CoIntelligence\ChunkData;
use App\CoIntelligence\DocChunkRepository;
use App\CoIntelligence\KnowledgeChunker;
use App\Identity\PillarService;
use App\Pages\PublishedPageRenderer;
use Waaseyaa\CLI\CliIO;

/**
 * `vendor/bin/waaseyaa app:ingest-knowledge [--dry-run] [--no-prune]` — build
 * the Co-Intelligence RAG knowledge base. Mirrors oiatc's app:ingest-docs:
 * extract heading-delimited passages from FNPI's own sources and upsert them
 * into anokii_doc_chunk, keyed by a stable chunk_key, so a re-run converges the
 * index to the current corpus. No embeddings are produced.
 *
 * Three sources:
 *   1. The bundled FNPI documents in resources/knowledge/ (markdown).
 *   2. The live public site copy: the PUBLISHED revision of each public `page`
 *      entity, rendered through the same page.html.twig the public routes
 *      serve (PublishedPageRenderer, shared with PageController). Draft
 *      revisions never reach the index — unpublished copy cannot leak into
 *      Co-Intelligence answers.
 *   3. The live Identity Workspace pillars from the database (so the assistant
 *      is grounded in the current pillar state, not a snapshot).
 *
 * If any public page has no published revision (a fresh or half-seeded
 * database), the run aborts non-zero BEFORE writing: a prune against a partial
 * corpus would silently delete the live page knowledge from the index.
 */
final class IngestKnowledgeCommand
{
    /** Bundled documents: file (under resources/knowledge) => [source_url, title]. */
    private const DOCS = [
        'fnpi-narrative.md' => ['knowledge/fnpi-narrative', 'FNPI Narrative'],
        'fnpi-master-plan.md' => ['knowledge/fnpi-master-plan', 'FNPI Master Plan'],
        'tech-lane-summary.md' => ['knowledge/tech-lane-summary', 'FNPI Tech Lane Summary'],
        'fnpi-tech-lane-business-model.md' => ['knowledge/fnpi-tech-lane-business-model', 'FNPI Tech Lane Business Model'],
        'procurement-vendor-narrative.md' => ['knowledge/procurement-vendor-narrative', 'FNPI Procurement and Vendor Narrative'],
    ];

    /** Live public pages: source_url (the entity `path`) => title. */
    private const PAGES = [
        '/' => 'FNPI Home (public site)',
        '/technology' => 'FNPI Technology (public site)',
        '/how-it-works' => 'FNPI How It Works (public site)',
        '/contact' => 'FNPI Contact (public site)',
        '/defence' => 'FNPI Defence &amp; Security (public site)',
        '/faraday' => 'FNPI Faraday cases (public site)',
    ];

    public function __construct(
        private readonly DocChunkRepository $chunks,
        private readonly PillarService $pillars,
        private readonly PublishedPageRenderer $pages,
        private readonly string $knowledgeDir,
        private readonly KnowledgeChunker $chunker = new KnowledgeChunker(),
    ) {}

    public function run(CliIO $io): int
    {
        $dryRun = (bool) $io->option('dry-run');
        $pruneOption = $io->option('prune');
        $prune = $pruneOption === null ? true : (bool) $pruneOption;

        [$chunks, $sources, $missingPages] = $this->collect($io);
        $io->writeln(sprintf('Extracted %d chunks from %d sources.', count($chunks), $sources));

        if ($missingPages !== []) {
            $io->error(sprintf(
                'Aborting without writing: no published page copy at %s. Seed and publish the pages (app:seed-pages) before ingesting; pruning a partial corpus would drop live page knowledge from the index.',
                implode(', ', $missingPages),
            ));

            return 1;
        }

        if ($dryRun) {
            foreach (array_slice($chunks, 0, 10) as $c) {
                $io->writeln(sprintf(
                    '  [%s] %s (%d chars)',
                    $c->title,
                    $c->heading !== '' ? $c->heading : '(intro)',
                    mb_strlen($c->text),
                ));
            }
            $io->writeln('Dry run: no changes written.');

            return 0;
        }

        $result = $this->chunks->sync($chunks, $prune);
        $io->writeln(sprintf(
            'anokii_doc_chunk sync: %d created, %d updated, %d deleted (%d total).',
            $result['created'],
            $result['updated'],
            $result['deleted'],
            $result['total'],
        ));

        return 0;
    }

    /**
     * @return array{0: list<ChunkData>, 1: int, 2: list<string>}
     */
    private function collect(CliIO $io): array
    {
        $chunks = [];
        $sources = 0;
        $missingPages = [];

        // 1. Bundled FNPI documents.
        foreach (self::DOCS as $file => [$sourceUrl, $title]) {
            $path = rtrim($this->knowledgeDir, '/\\') . '/' . $file;
            if (!is_file($path)) {
                $io->error(sprintf('Skipped %s: file not found at %s.', $title, $path));
                continue;
            }
            $markdown = (string) file_get_contents($path);
            $docChunks = $this->chunker->chunkMarkdown($markdown, $sourceUrl, $title);
            if ($docChunks !== []) {
                $sources++;
            }
            foreach ($docChunks as $c) {
                $chunks[] = $c;
            }
        }

        // 2. Live public site copy: published page-entity revisions only.
        foreach (self::PAGES as $sourceUrl => $title) {
            try {
                $html = $this->pages->render($sourceUrl);
            } catch (\Throwable $e) {
                $io->error(sprintf('%s: could not render %s (%s).', $title, $sourceUrl, $e->getMessage()));
                $missingPages[] = $sourceUrl;
                continue;
            }
            if ($html === null) {
                $io->error(sprintf('%s: no page or no published revision at %s.', $title, $sourceUrl));
                $missingPages[] = $sourceUrl;
                continue;
            }
            $pageChunks = $this->chunker->chunkHtml($html, $sourceUrl, $title);
            if ($pageChunks !== []) {
                $sources++;
            }
            foreach ($pageChunks as $c) {
                $chunks[] = $c;
            }
        }

        // 3. Live Identity Workspace pillars.
        $pillarChunks = $this->pillarChunks();
        if ($pillarChunks !== []) {
            $sources++;
        }
        foreach ($pillarChunks as $c) {
            $chunks[] = $c;
        }

        return [$chunks, $sources, $missingPages];
    }

    /**
     * One chunk per pillar: its current title, body, decision, and notes, so the
     * assistant can answer from the live Identity Workspace state.
     *
     * @return list<ChunkData>
     */
    private function pillarChunks(): array
    {
        $chunks = [];
        foreach ($this->pillars->listPillars() as $pillar) {
            $parts = [];
            foreach ([$pillar->getNowLabel(), $pillar->getBody(), $pillar->getDecision(), $pillar->getNotes()] as $value) {
                $value = trim($value);
                if ($value !== '') {
                    $parts[] = $value;
                }
            }
            $text = implode('. ', $parts);
            if (trim($text) === '') {
                continue;
            }
            $heading = $pillar->getTitle();
            foreach ($this->chunker->chunkText($text, '/anokii/identity', 'Identity Workspace: ' . $heading, $heading) as $c) {
                $chunks[] = $c;
            }

            // Peer-language (Anishinaabemowin) pillar content, so the knowledge
            // base is bilingual: the agent can ground a page's Anishinaabemowin
            // copy in the pillar's own translation, and the chat can answer in
            // both languages. Only languages actually translated are ingested.
            foreach (PillarService::TRANSLATIONS as $langcode => $endonym) {
                $translation = $this->pillars->getTranslation($pillar->getPid(), $langcode);
                if ($translation === null) {
                    continue;
                }
                $ojText = trim($translation->getTitle() . '. ' . $translation->getBody());
                if (trim($ojText, " .\t\n") === '') {
                    continue;
                }
                $ojHeading = $endonym . ': ' . $translation->getTitle();
                foreach ($this->chunker->chunkText($ojText, '/anokii/identity', 'Identity Workspace (' . $endonym . '): ' . $heading, $ojHeading) as $c) {
                    $chunks[] = $c;
                }
            }
        }

        return $chunks;
    }
}
