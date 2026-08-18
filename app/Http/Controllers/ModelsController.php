<?php

namespace App\Http\Controllers;

use App\Ai\AiProviderInterface;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/models — proxy ke LimitRouter GET /models (server-side).
 * Hanya kembalikan data model yang aman (id+label). Tidak ada key/billing/auth.
 */
final class ModelsController extends Controller
{
    public function __construct(private readonly AiProviderInterface $provider) {}

    public function index(): JsonResponse
    {
        $models = $this->provider->listModels();

        return response()->json(['models' => $models]);
    }
}
