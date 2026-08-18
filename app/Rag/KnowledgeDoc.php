<?php

namespace App\Rag;

/**
 * DTO untuk satu dokumen knowledge yang sudah di-load.
 */
final class KnowledgeDoc
{
    public function __construct(
        public readonly string $sourceId,
        public readonly string $title,
        public readonly string $category,
        public readonly ?string $sourceUrl,
        public readonly string $sourceStatus,
        public readonly string $content,
    ) {}

    /**
     * Teks yang dipakai untuk retrieval (title + body).
     */
    public function searchableText(): string
    {
        return strtolower($this->title.' '.$this->content);
    }
}
