<?php

namespace App\Rag;

/**
 * Hasil retrieval untuk satu dokumen.
 */
final class RetrievedSource
{
    public function __construct(
        public readonly string $sourceId,
        public readonly string $title,
        public readonly ?string $sourceUrl,
        public readonly string $content,
        public readonly float $score,
    ) {}
}
