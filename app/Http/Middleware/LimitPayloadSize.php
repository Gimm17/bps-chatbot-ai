<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi ukuran body mentah untuk mitigasi payload berlebihan.
 */
final class LimitPayloadSize
{
    private const MAX_BYTES = 64 * 1024; // 64KB cukup untuk chat demo

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') || $request->isMethod('PUT')) {
            $size = (int) (strlen((string) $request->getContent()) ?: strlen((string) file_get_contents('php://input')));
            if ($size > self::MAX_BYTES) {
                return response()->json([
                    'error' => [
                        'code' => 'INVALID_INPUT',
                        'message' => 'Permintaan terlalu besar.',
                    ],
                ], 400);
            }
        }

        return $next($request);
    }
}
