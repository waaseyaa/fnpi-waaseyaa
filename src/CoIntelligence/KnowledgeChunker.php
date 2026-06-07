<?php

declare(strict_types=1);

namespace App\CoIntelligence;

/**
 * Turns a source (rendered HTML page, a markdown document, or a block of plain
 * text) into stable, heading-delimited retrieval chunks. Pure and
 * deterministic: same input always yields the same chunks and the same stable
 * keys, so ingestion can upsert on the key rather than duplicate. No storage,
 * no embeddings.
 *
 * Mirrors oiatc's DocChunker for the HTML path (extract from the first <main>,
 * else <body>, skipping script/style/svg; h1/h2/h3 start sections) and adds a
 * markdown path (lines starting with #/##/### start sections). Oversized
 * sections split on word boundaries into "part 0", "part 1", ...
 */
final class KnowledgeChunker
{
    /** Soft cap on chunk length; oversized sections split on word boundaries. */
    public const MAX_CHARS = 1500;

    /** Drop fragments shorter than this (stray labels, empty sections). */
    public const MIN_CHARS = 30;

    /**
     * @return list<ChunkData>
     */
    public function chunkHtml(string $html, string $sourceUrl, string $title): array
    {
        $dom = $this->loadHtml($html);
        $root = $this->contentRoot($dom);
        if ($root === null) {
            return [];
        }

        /** @var list<array{heading: string, text: string}> $sections */
        $sections = [];
        $current = ['heading' => '', 'buffer' => ''];

        $flush = static function (array &$sections, array &$current): void {
            $text = self::normalize($current['buffer']);
            if ($text !== '') {
                $sections[] = ['heading' => $current['heading'], 'text' => $text];
            }
            $current['buffer'] = '';
        };

        $this->walk($root, $sections, $current, $flush);
        $flush($sections, $current);

        return $this->sectionsToChunks($sections, $sourceUrl, $title);
    }

    /**
     * Chunk a markdown document. ATX headings (#, ##, ###) open new sections;
     * the first heading text also becomes the document title when none is given.
     *
     * @return list<ChunkData>
     */
    public function chunkMarkdown(string $markdown, string $sourceUrl, string $title): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];

        /** @var list<array{heading: string, text: string}> $sections */
        $sections = [];
        $heading = '';
        $buffer = '';

        $flush = static function () use (&$sections, &$heading, &$buffer): void {
            $text = self::normalize($buffer);
            if ($text !== '') {
                $sections[] = ['heading' => $heading, 'text' => $text];
            }
            $buffer = '';
        };

        foreach ($lines as $line) {
            if (preg_match('/^\s{0,3}#{1,4}\s+(.*)$/', $line, $m) === 1) {
                $flush();
                $heading = self::normalize($this->stripInline($m[1]));
                continue;
            }
            $buffer .= ' ' . $this->stripInline($line);
        }
        $flush();

        return $this->sectionsToChunks($sections, $sourceUrl, $title);
    }

    /**
     * Chunk a block of plain text under a single heading (e.g. a pillar body).
     *
     * @return list<ChunkData>
     */
    public function chunkText(string $text, string $sourceUrl, string $title, string $heading): array
    {
        $normalized = self::normalize($text);
        if ($normalized === '') {
            return [];
        }

        return $this->sectionsToChunks([['heading' => $heading, 'text' => $normalized]], $sourceUrl, $title);
    }

    /**
     * @param list<array{heading: string, text: string}> $sections
     *
     * @return list<ChunkData>
     */
    private function sectionsToChunks(array $sections, string $sourceUrl, string $title): array
    {
        $chunks = [];
        $usedKeys = [];

        foreach ($sections as $section) {
            foreach ($this->splitToSize($section['text']) as $partIndex => $partText) {
                if (mb_strlen($partText) < self::MIN_CHARS) {
                    continue;
                }
                $key = $this->chunkKey($sourceUrl, $section['heading'], $partIndex, $usedKeys);
                $chunks[] = new ChunkData(
                    chunkKey: $key,
                    sourceUrl: $sourceUrl,
                    title: $title,
                    heading: $section['heading'],
                    text: $partText,
                );
            }
        }

        return $chunks;
    }

    /**
     * @param array{heading: string, text: string}[] $sections
     * @param array{heading: string, buffer: string} $current
     */
    private function walk(\DOMNode $node, array &$sections, array &$current, callable $flush): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'svg', 'noscript', 'template'], true)) {
                    continue;
                }
                if (in_array($tag, ['h1', 'h2', 'h3'], true)) {
                    $flush($sections, $current);
                    $current['heading'] = self::normalize($child->textContent);
                    continue;
                }
                $this->walk($child, $sections, $current, $flush);
                continue;
            }
            if ($child instanceof \DOMText) {
                $current['buffer'] .= ' ' . $child->wholeText;
            }
        }
    }

    /**
     * Split text into <= MAX_CHARS parts on word boundaries (never mid-word).
     *
     * @return list<string>
     */
    private function splitToSize(string $text): array
    {
        if (mb_strlen($text) <= self::MAX_CHARS) {
            return [$text];
        }

        $parts = [];
        $buffer = '';
        foreach (explode(' ', $text) as $word) {
            $candidate = $buffer === '' ? $word : $buffer . ' ' . $word;
            if (mb_strlen($candidate) > self::MAX_CHARS && $buffer !== '') {
                $parts[] = $buffer;
                $buffer = $word;
                continue;
            }
            $buffer = $candidate;
        }
        if ($buffer !== '') {
            $parts[] = $buffer;
        }

        return $parts;
    }

    /**
     * Stable, human-readable key: {source}#{heading-slug}-{part}. Disambiguated
     * with a numeric suffix only if a source repeats a heading.
     *
     * @param array<string, true> $usedKeys
     */
    private function chunkKey(string $sourceUrl, string $heading, int $partIndex, array &$usedKeys): string
    {
        $base = $sourceUrl . '#' . ($heading === '' ? 'intro' : $this->slug($heading)) . '-' . $partIndex;
        $key = $base;
        $dup = 1;
        while (isset($usedKeys[$key])) {
            $key = $base . '_' . (++$dup);
        }
        $usedKeys[$key] = true;

        return $key;
    }

    /** Strip the most common inline markdown so passages read as plain prose. */
    private function stripInline(string $text): string
    {
        // Links [label](url) -> label; images ![alt](url) -> alt.
        $text = preg_replace('/!?\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? $text;
        // Bold/italic/code markers and list/quote bullets.
        $text = str_replace(['**', '__', '`'], '', $text);

        return preg_replace('/^\s*([*\-+]|\d+\.)\s+/', '', $text) ?? $text;
    }

    private function slug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return mb_substr($slug, 0, 80);
    }

    private static function normalize(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    private function loadHtml(string $html): \DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    private function contentRoot(\DOMDocument $dom): ?\DOMNode
    {
        $main = $dom->getElementsByTagName('main')->item(0);
        if ($main !== null) {
            return $main;
        }

        return $dom->getElementsByTagName('body')->item(0);
    }
}
