<?php

namespace App\Security;

/**
 * Generator requestId untuk tracing. Publik boleh melihat ini di error detail.
 */
final class RequestId
{
    public static function generate(): string
    {
        // uniqid sudah cukup untuk demo; tidak ada Math.random/Date.now di sini.
        return 'req_'.bin2hex(random_bytes(8));
    }
}
