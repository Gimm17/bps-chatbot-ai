<?php

namespace App\Bps;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Http;

/**
 * Satu-satunya yang menyentuh webapi.bps.go.id.
 * Auth: key via path segment (BPS convention); dataexim pakai query param.
 * Cache: 24h per URL, hanya cache response OK (error tidak di-cache).
 *
 * ponytail: cache key = md5(url). Upgrade ke tag-grouped cache bila perlu invalidasi per-domain.
 */
final class BpsApiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $key,
        private readonly int $timeoutSecs,
        private readonly int $cacheTtlHours,
        private readonly string $cachePrefix,
        private readonly bool $cacheEnabled,
        private readonly Repository $cache,
    ) {}

    /** Path-segment style: /v1/api/{path}/key/{key}. */
    public function get(string $pathTemplate, array $params): BpsResponse
    {
        return $this->execute($this->buildPathUrl($pathTemplate, $params));
    }

    /** Query-param style (dataexim): /v1/api/{path}?...&key={key}. */
    public function getQuery(string $pathTemplate, array $params): BpsResponse
    {
        return $this->execute($this->buildQueryUrl($pathTemplate, $params));
    }

    private function execute(string $url): BpsResponse
    {
        if ($this->cacheEnabled) {
            $cached = $this->cache->get($this->cachePrefix . md5($url));
            if (is_string($cached) && $cached !== '') {
                return BpsResponse::fromCached($cached);
            }
        }

        try {
            $resp = Http::timeout($this->timeoutSecs)->get($url);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new BpsApiException('BPS API connection failed: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new BpsApiException('BPS API request failed: ' . $e->getMessage(), 0, $e);
        }

        $parsed = BpsResponse::parse($resp->json() ?? [], $resp->status());

        if ($this->cacheEnabled && $parsed->isOk) {
            $this->cache->put($this->cachePrefix . md5($url), $parsed->toJson(), now()->addHours($this->cacheTtlHours));
        }

        return $parsed;
    }

    private function buildPathUrl(string $pathTemplate, array $params): string
    {
        $segments = ['v1/api'];
        foreach (explode('/', trim($pathTemplate, '/')) as $seg) {
            if ($seg !== '') {
                $segments[] = $seg;
            }
        }
        foreach ($params as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $segments[] = $k;
            $segments[] = (string) $v;
        }
        $segments[] = 'key';
        $segments[] = $this->key;

        return rtrim($this->baseUrl, '/') . '/' . implode('/', $segments);
    }

    private function buildQueryUrl(string $pathTemplate, array $params): string
    {
        $query = array_filter($params, fn ($v) => $v !== null && $v !== '');
        $query['key'] = $this->key;

        return rtrim($this->baseUrl, '/') . '/v1/api/' . trim($pathTemplate, '/') . '?' . http_build_query($query);
    }
}
