<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/feedback — demo. Tidak persisten (no DB/login untuk demo).
 * ponytail: log saja untuk demo. Upgrade ke tabel feedback + moderation.
 */
final class FeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'messageId' => ['nullable', 'string', 'max:64'],
            'rating' => ['required', 'string', 'in:helpful,not_helpful'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        logger()->info('bps-ai feedback', [
            'messageId' => $data['messageId'] ?? null,
            'rating' => $data['rating'],
        ]);

        return response()->json(['status' => 'ok']);
    }
}
