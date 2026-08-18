<?php

namespace App\Security;

use Illuminate\Support\Facades\Cache;

/**
 * Rate limiter demo berbasis IP via cache store.
 * ponytail: demo, IP-only bukan production SLA. Upgrade ke signed-token + per-identity.
 */
final class RateLimiter
{
    public function __construct(
        private readonly int $maxPerMinute = 10,
    ) {}

    public function tooManyAttempts(string $key): bool
    {
        $cacheKey = 'bps:rate:'.$key.':'.floor(time() / 60);

        try {
            $count = (int) Cache::get($cacheKey, 0);
            if ($count >= $this->maxPerMinute) {
                return true;
            }
            Cache::put($cacheKey, $count + 1, 70);

            return false;
        } catch (\Throwable) {
            // Bila cache store tidak siap (demo tanpa DB), jangan blokir.
            return false;
        }
    }

    public function keyFor(string $ip, ?string $conversationId = null): string
    {
        return sha1($ip.'|'.($conversationId ?? ''));
    }
}
