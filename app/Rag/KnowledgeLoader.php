<?php

namespace App\Rag;

/**
 * Memuat file knowledge markdown dari data/knowledge/.
 * Frontmatter dipakai untuk metadata (sourceId, title, url, status).
 *
 * ponytail: frontmatter di-parse manual (key: value) tanpa dependency YAML.
 * Upgrade: bila butuh nested/array, ganti ke symfony/yaml.
 */
final class KnowledgeLoader
{
    public function __construct(
        private readonly string $basePath,
    ) {}

    /**
     * @return list<KnowledgeDoc>
     */
    public function load(): array
    {
        if (! is_dir($this->basePath)) {
            return [];
        }

        $docs = [];
        foreach (glob($this->basePath.'/*.md') as $file) {
            if (basename($file) === 'README.md') {
                continue;
            }
            if ($doc = $this->parseFile($file)) {
                $docs[] = $doc;
            }
        }

        return $docs;
    }

    private function parseFile(string $file): ?KnowledgeDoc
    {
        $raw = (string) file_get_contents($file);
        if ($raw === '') {
            return null;
        }

        $meta = [];
        $body = $raw;
        if (preg_match('/^\s*---\s*\n(.*?)\n---\s*\n?(.*)$/s', $raw, $m)) {
            $meta = $this->parseFrontmatter($m[1]);
            $body = $m[2];
        }

        $sourceId = (string) ($meta['id'] ?? basename($file, '.md'));
        $title = (string) ($meta['title'] ?? $sourceId);
        $category = (string) ($meta['category'] ?? 'definition');
        $sourceUrl = isset($meta['source_url']) && $meta['source_url'] !== ''
            ? (string) $meta['source_url']
            : null;
        $status = (string) ($meta['source_status'] ?? 'DEMO_NOT_VERIFIED');

        return new KnowledgeDoc(
            sourceId: $sourceId,
            title: $title,
            category: $category,
            sourceUrl: $sourceUrl,
            sourceStatus: $status,
            content: trim($body),
        );
    }

    /**
     * Parser frontmatter sederhana: satu `key: value` per baris.
     *
     * @return array<string,string>
     */
    private function parseFrontmatter(string $block): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $block) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$k, $v] = explode(':', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if ($k === '') {
                continue;
            }
            // buang quote opsional
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[strlen($v) - 1] === $v[0]) {
                $v = substr($v, 1, -1);
            }
            $out[$k] = $v;
        }

        return $out;
    }
}
