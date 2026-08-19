<?php

namespace App\Http\Controllers;

use App\Ai\ChatService;
use App\Security\InputValidator;
use App\Security\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/chat — full flow didelegasikan ke ChatService.
 * Controller hanya urus: validate, rate limit, sanitasi error.
 */
final class ChatController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly InputValidator $validator,
        private readonly RateLimiter $rateLimiter,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $conversationId = (string) $request->input('conversationId', '');
        $message = $request->input('message');

        // 1. Input validation.
        if ($this->validator->validateMessage(is_string($message) ? $message : null) !== []) {
            return $this->error('INVALID_INPUT', 'Pesan tidak valid atau kosong.', 400);
        }

        // 2. Rate limit (per IP + conversation).
        $ip = $request->ip() ?? '0.0.0.0';
        if ($this->rateLimiter->tooManyAttempts($this->rateLimiter->keyFor($ip, $conversationId))) {
            return $this->error(
                'RATE_LIMITED',
                'Terlalu banyak permintaan. Silakan coba beberapa saat lagi.',
                429,
            );
        }

        // 3. Orchestrate. conversationId memungkinkan multi-turn context
        //    (backend mengingat bubble sebelumnya dalam sesi yang sama).
        $response = $this->chatService->handle((string) $message, $conversationId);

        $status = $response->status;
        $code = match ($status) {
            'rate_limited' => 429,
            'provider_error' => 503,
            default => 200,
        };

        return response()->json($response, $code);
    }

    private function error(string $code, string $message, int $http): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $http);
    }
}
