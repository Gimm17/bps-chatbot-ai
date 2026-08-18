<?php

namespace App\Bps;

/**
 * DTO hasil parse response BPS WebAPI.
 * BPS return HTTP 200 + body {"status":"Error",...} untuk error —
 * cek field status, BUKAN HTTP code.
 * Format data: [ {page,pages,total,count,per_page}, [ ...rows... ] ]
 */
final class BpsResponse
{
    /**
     * @param  list<array<string,mixed>>  $rows
     */
    public function __construct(
        public readonly bool $isOk,
        public readonly array $rows,
        public readonly int $pages,
        public readonly int $total,
        public readonly ?string $errorMessage,
        public readonly int $httpStatus,
    ) {}

    /** @param array<string,mixed> $body */
    public static function parse(array $body, int $httpStatus): self
    {
        $status = (string) ($body['status'] ?? '');
        $availability = (string) ($body['data-availability'] ?? '');

        if ($status !== 'OK' || $availability !== 'available') {
            $msg = (string) ($body['message'] ?? ($body['message2'] ?? 'BPS API returned non-OK status'));
            return new self(false, [], 0, 0, $msg, $httpStatus);
        }

        $data = $body['data'] ?? [];
        $meta = is_array($data) && isset($data[0]) && is_array($data[0]) ? $data[0] : [];
        $rows = is_array($data) && isset($data[1]) && is_array($data[1]) ? $data[1] : [];

        return new self(
            isOk: true,
            rows: $rows,
            pages: (int) ($meta['pages'] ?? 1),
            total: (int) ($meta['total'] ?? count($rows)),
            errorMessage: null,
            httpStatus: $httpStatus,
        );
    }

    public static function fromCached(string $json): self
    {
        $a = json_decode($json, true) ?: [];
        return new self(
            isOk: (bool) ($a['isOk'] ?? false),
            rows: (array) ($a['rows'] ?? []),
            pages: (int) ($a['pages'] ?? 0),
            total: (int) ($a['total'] ?? 0),
            errorMessage: $a['errorMessage'] ?? null,
            httpStatus: (int) ($a['httpStatus'] ?? 200),
        );
    }

    public function toJson(): string
    {
        return (string) json_encode([
            'isOk' => $this->isOk, 'rows' => $this->rows,
            'pages' => $this->pages, 'total' => $this->total,
            'errorMessage' => $this->errorMessage, 'httpStatus' => $this->httpStatus,
        ], JSON_UNESCAPED_UNICODE);
    }
}
