<?php

namespace App\Bps;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;
use Stringable;

/** Menambahkan citation backend ke tool result dan menyimpannya untuk response akhir. */
final class CitationCollectingTool implements Tool
{
    public function __construct(
        private readonly Tool $delegate,
        private readonly Closure $collect,
    ) {}

    public function name(): string
    {
        return ToolNameResolver::resolve($this->delegate);
    }

    public function description(): Stringable|string
    {
        return $this->delegate->description();
    }

    public function handle(Request $request): Stringable|string
    {
        $raw = (string) $this->delegate->handle($request);
        $result = json_decode($raw, true);
        if (! is_array($result) || ($result['status'] ?? null) !== 'ok') {
            return $raw;
        }

        $citations = $this->extractCitations($result);
        foreach ($citations as $citation) {
            ($this->collect)($citation);
        }

        if ($citations !== []) {
            $result['_citations'] = array_map(fn (BpsCitation $citation) => [
                'sourceId' => $citation->sourceId,
                'title' => $citation->title,
                'url' => $citation->url,
                'snippet' => $citation->snippet,
                'domain' => $citation->domain,
                'period' => $citation->period,
            ], $citations);
        }

        return (string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->delegate->schema($schema);
    }

    /** @return list<BpsCitation> */
    private function extractCitations(array $result): array
    {
        $citations = [];
        foreach ((array) ($result['domains'] ?? []) as $domain) {
            if (! is_array($domain) || empty($domain['domain_id'])) {
                continue;
            }
            $citations[] = new BpsCitation(
                sourceId: (string) $domain['domain_id'],
                title: (string) ($domain['domain_name'] ?? $domain['domain_id']),
                url: isset($domain['domain_url']) ? (string) $domain['domain_url'] : null,
                snippet: null,
                domain: isset($domain['domain_name']) ? (string) $domain['domain_name'] : null,
            );
        }

        foreach ($this->publicationRows($result) as $publication) {
            $id = $publication['pub_id'] ?? $publication['id'] ?? null;
            if ($id === null || $id === '') {
                continue;
            }
            $citations[] = new BpsCitation(
                sourceId: (string) $id,
                title: (string) ($publication['title'] ?? $id),
                url: isset($publication['pdf']) ? (string) $publication['pdf'] : null,
                snippet: isset($publication['abstract']) ? trim(strip_tags((string) $publication['abstract'])) : null,
                period: isset($publication['rl_date']) ? (string) $publication['rl_date'] : null,
            );
        }

        if (isset($result['domain'], $result['var_id'], $result['period_id'])) {
            $id = sprintf('data:%s:%s:%s', $result['domain'], $result['var_id'], $result['period_id']);
            $variable = is_array($result['variable'][0] ?? null) ? $result['variable'][0] : [];
            $citations[] = new BpsCitation(
                sourceId: $id,
                title: (string) ($variable['label'] ?? "Data BPS {$result['var_id']}"),
                url: null,
                snippet: null,
                domain: (string) $result['domain'],
                period: (string) $result['period_id'],
            );
        }

        return $citations;
    }

    /** @return list<array<string, mixed>> */
    private function publicationRows(array $result): array
    {
        $rows = [];
        foreach (['publication', 'press_release', 'static_table'] as $key) {
            if (is_array($result[$key] ?? null)) {
                $rows[] = $result[$key];
            }
        }
        foreach (['publications', 'press_releases', 'static_tables'] as $key) {
            foreach ((array) ($result[$key] ?? []) as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }
}
