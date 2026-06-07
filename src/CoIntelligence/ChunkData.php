<?php

declare(strict_types=1);

namespace App\CoIntelligence;

/**
 * One extracted knowledge chunk before it is stored: a stable key plus the
 * passage text and its provenance (source label/url + heading). Pure value
 * object so chunking can be tested without storage.
 */
final readonly class ChunkData
{
    public function __construct(
        public string $chunkKey,
        public string $sourceUrl,
        public string $title,
        public string $heading,
        public string $text,
    ) {}
}
