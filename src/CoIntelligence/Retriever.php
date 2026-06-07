<?php

declare(strict_types=1);

namespace App\CoIntelligence;

use Waaseyaa\Database\DatabaseInterface;

/**
 * Keyword retrieval over the anokii_doc_chunk knowledge base, mirroring oiatc's
 * GraphRetriever scoring without the geographic graph (FNPI is a single
 * vantage). Given a question, tokenize and stopword-filter it, score every
 * chunk by weighted term frequency (title x3, heading x2, text x1) with
 * logarithmic damping, gate out weak matches, and return the top-K passages,
 * best first.
 *
 * A vector index over the same rows could implement the same retrieve() shape
 * later without the chat layer changing.
 */
final class Retriever
{
    /** Common words that carry no retrieval signal. */
    private const STOPWORDS = [
        'the', 'a', 'an', 'and', 'or', 'but', 'of', 'to', 'in', 'on', 'for', 'is', 'are',
        'was', 'were', 'be', 'been', 'with', 'as', 'by', 'at', 'from', 'that', 'this',
        'it', 'its', 'we', 'our', 'you', 'your', 'i', 'me', 'my', 'they', 'them', 'their',
        'what', 'which', 'who', 'how', 'when', 'where', 'why', 'do', 'does', 'did', 'can',
        'could', 'would', 'should', 'will', 'about', 'into', 'over', 'than', 'then', 'so',
        'if', 'not', 'no', 'yes', 'us', 'has', 'have', 'had', 'all', 'any', 'each', 'more',
    ];

    public function __construct(private readonly DatabaseInterface $db) {}

    /**
     * @return list<Passage> up to $k passages, highest score first
     */
    public function retrieve(string $query, int $k = 6): array
    {
        $terms = $this->tokenize($query);
        if ($terms === []) {
            return [];
        }

        $scored = [];
        foreach ((new DocChunkRepository($this->db))->all() as $chunk) {
            $score = $this->score($terms, $chunk);
            if ($score <= 0.0) {
                continue;
            }
            $scored[] = new Passage(
                sourceUrl: $chunk['source_url'],
                title: $chunk['title'],
                heading: $chunk['heading'],
                text: $chunk['text'],
                score: $score,
            );
        }

        if ($scored === []) {
            return [];
        }

        usort($scored, static fn(Passage $a, Passage $b): int => $b->score <=> $a->score);

        // Relevance gate: keep only passages within half the top score, so a
        // strong question does not drag in weakly-matching padding.
        $max = $scored[0]->score;
        $gate = $max * 0.45;
        $kept = array_values(array_filter($scored, static fn(Passage $p): bool => $p->score >= $gate));

        return array_slice($kept, 0, $k);
    }

    /**
     * @param array<string,int> $terms term => weight (query repetition)
     * @param array{title:string,heading:string,text:string} $chunk
     */
    private function score(array $terms, array $chunk): float
    {
        $title = ' ' . $this->lower($chunk['title']) . ' ';
        $heading = ' ' . $this->lower($chunk['heading']) . ' ';
        $text = ' ' . $this->lower($chunk['text']) . ' ';

        $score = 0.0;
        foreach ($terms as $term => $_weight) {
            $needle = ' ' . $term;
            $inTitle = substr_count($title, $needle);
            $inHeading = substr_count($heading, $needle);
            $inText = substr_count($text, $needle);
            if ($inTitle === 0 && $inHeading === 0 && $inText === 0) {
                continue;
            }
            // Logarithmic damping so a term repeated many times does not dominate.
            $score += 3.0 * $this->damp($inTitle)
                + 2.0 * $this->damp($inHeading)
                + 1.0 * $this->damp($inText);
        }

        return $score;
    }

    private function damp(int $count): float
    {
        return $count > 0 ? 1.0 + log((float) $count) : 0.0;
    }

    /**
     * @return array<string,int> distinct query terms => 1
     */
    private function tokenize(string $query): array
    {
        $query = $this->lower($query);
        $words = preg_split('/[^a-z0-9]+/', $query) ?: [];
        $terms = [];
        foreach ($words as $word) {
            if (mb_strlen($word) < 3 || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            $terms[$word] = 1;
        }

        return $terms;
    }

    private function lower(string $value): string
    {
        return mb_strtolower($value);
    }
}
