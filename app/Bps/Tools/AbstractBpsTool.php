<?php

namespace App\Bps\Tools;

use App\Bps\BpsApiClient;
use App\Bps\BpsApiException;

abstract class AbstractBpsTool
{
    protected const MAX_RESULTS = 100;

    public function __construct(protected readonly BpsApiClient $client) {}

    protected function list(string $path, array $params, string $resultKey, bool $query = false): string
    {
        try {
            $response = $query
                ? $this->client->getQuery($path, $params)
                : $this->client->get($path, $params);
        } catch (BpsApiException $e) {
            return $this->error($e->getMessage());
        }

        if (! $response->isOk) {
            return $this->error($response->errorMessage ?? 'BPS API error');
        }

        $rows = array_values(array_filter($response->rows, 'is_array'));
        $upstreamError = $rows[0] ?? [];
        if (($upstreamError['condition'] ?? null) === 'ERROR') {
            return $this->error((string) ($upstreamError['message'] ?? 'BPS API error'));
        }

        $total = max($response->total, count($rows));
        $rows = array_slice($rows, 0, self::MAX_RESULTS);
        $returned = count($rows);

        return $this->json([
            'status' => 'ok',
            'total' => $total,
            'returned' => $returned,
            'truncated' => $total > $returned,
            $resultKey => $rows,
        ]);
    }

    protected function detail(string $path, array $params, string $resultKey): string
    {
        try {
            $response = $this->client->get($path, $params);
        } catch (BpsApiException $e) {
            return $this->error($e->getMessage());
        }

        if (! $response->isOk) {
            return $this->error($response->errorMessage ?? 'BPS API error');
        }

        $row = current(array_filter($response->rows, 'is_array')) ?: [];

        return $this->json(['status' => 'ok', $resultKey => $row]);
    }

    protected function optional(array $arguments, array $keys): array
    {
        $params = [];
        foreach ($keys as $key) {
            $value = $arguments[$key] ?? null;
            if ($value !== null && $value !== '') {
                $params[$key] = (string) $value;
            }
        }

        return $params;
    }

    protected function error(string $message): string
    {
        return $this->json(['status' => 'error', 'message' => $message]);
    }

    protected function json(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
