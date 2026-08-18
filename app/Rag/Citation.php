<?php

namespace App\Rag;

/**
 * DTO citation yang aman untuk dikirim ke client.
 * URL hanya dari registry backend, tidak dari output LLM.
 */
final class Citation
{
    public function __construct(
        public readonly string $sourceId,
        public readonly string $title,
        public readonly ?string $url,
        public readonly ?string $snippet,
        public readonly bool $verified = false,
    ) {}

    /**
     * @param  list<RetrievedSource>  $sources
     * @param  list<string>  $sourceIds
     * @return list<Citation>
     */
    public static function fromSources(array $sources, array $sourceIds): array
    {
        $byId = [];
        foreach ($sources as $s) {
            $byId[$s->sourceId] = $s;
        }

        $out = [];
        $seen = [];
        foreach ($sourceIds as $id) {
            $id = trim($id);
            if ($id === '' || isset($seen[$id]) || ! isset($byId[$id])) {
                continue;
            }
            $seen[$id] = true;
            $src = $byId[$id];
            $out[] = new self(
                sourceId: $src->sourceId,
                title: $src->title,
                url: $src->sourceUrl,
                snippet: self::makeSnippet($src->content),
                verified: false, // demo: semua DEMO_NOT_VERIFIED, jangan klaim verified
            );
        }

        return $out;
    }

    /**
     * Map BPS source ids → Citation (verified:true).
     * Hanya id yang ada di $sources dipetakan (LLM tak bisa minta id asing).
     *
     * @param  array<string, \App\Bps\BpsCitation>  $sources
     * @param  list<string>  $sourceIds
     * @return list<Citation>
     */
    public static function fromBpsSources(array $sources, array $sourceIds): array
    {
        $out = [];
        $seen = [];
        foreach ($sourceIds as $id) {
            if (! is_string($id)) {
                continue;
            }
            $id = trim($id);
            if ($id === '' || isset($seen[$id]) || ! isset($sources[$id])) {
                continue;
            }
            $seen[$id] = true;
            $s = $sources[$id];
            if (! $s instanceof \App\Bps\BpsCitation) {
                continue;
            }
            $out[] = new self(
                sourceId: $s->sourceId,
                title: $s->title,
                url: $s->url,
                snippet: $s->snippet,
                verified: true,
            );
        }

        return $out;
    }

    private static function makeSnippet(string $content): string
    {
        // ambil paragraf pertama non-heading, max ~180 char
        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (mb_strlen($line) > 180) {
                return mb_substr($line, 0, 177).'...';
            }

            return $line;
        }

        return mb_substr(trim(strip_tags($content)), 0, 180);
    }
}
