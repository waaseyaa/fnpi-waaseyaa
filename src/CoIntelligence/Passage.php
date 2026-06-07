<?php

declare(strict_types=1);

namespace App\CoIntelligence;

/**
 * One retrieved passage: a stored chunk plus the score the retriever gave it.
 * Plain value object so retrieval can be tested and the answer layer has a
 * stable shape regardless of which retriever produced it.
 */
final readonly class Passage
{
    public function __construct(
        public string $sourceUrl,
        public string $title,
        public string $heading,
        public string $text,
        public float $score,
    ) {}
}
