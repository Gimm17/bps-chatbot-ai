<?php

namespace App\Ai;

use Illuminate\Support\Facades\Http;

use function Laravel\Ai\agent;

/**
 * Adapter LimitRouter via Laravel AI SDK (openai-compatible driver).
 * Server-side only. Browser tidak pernah melihat key/header/provider internals.
 *
 * Untuk chat: pakai agent() helper dengan provider 'limitrouter'.
 * Untuk /models: panggil GET {base}/models via HTTP (SDK tidak expose list models).
 */
final class LimitRouterProvider implements AiProviderInterface
{
    public function chat(ChatProviderInput $input): ChatProviderOutput
    {
        $timeout = $input->timeout ?? (int) config('ai.app.timeout', 30);
        $model = $input->model ?? (string) config('ai.app.default_model', 'gpt-4o-mini');

        // $input->messages = [Message(User, question)]; $input->instructions = system+evidence.
        $agent = agent(
            instructions: $input->instructions ?? '',
            messages: $input->messages,
        );

        $response = $agent->prompt(
            '', // pesan user sudah ada di messages
            provider: 'limitrouter',
            model: $model,
            timeout: $timeout,
        );

        return new ChatProviderOutput(
            text: (string) $response->text,
            model: $model,
        );
    }

    public function listModels(): array
    {
        $base = (string) config('ai.providers.limitrouter.url', 'https://limitrouter.com/v1');
        $key = (string) config('ai.providers.limitrouter.key', '');

        try {
            $resp = Http::withToken($key)
                ->timeout(15)
                ->get(rtrim($base, '/').'/models');

            if (! $resp->successful()) {
                return $this->fallbackModel();
            }

            $data = $resp->json();
            $models = $data['data'] ?? ($data['models'] ?? []);

            $out = [];
            foreach ((array) $models as $m) {
                $id = is_string($m) ? $m : (string) ($m['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $out[] = ['id' => $id, 'label' => $id];
            }

            return $out !== [] ? $out : $this->fallbackModel();
        } catch (\Throwable) {
            return $this->fallbackModel();
        }
    }

    /**
     * Bila /models tidak dapat dijangkau, kembalikan default dari config
     * agar UI tetap bisa jalan (dengan label DEMO jelas).
     *
     * @return list<array{id:string,label:string}>
     */
    private function fallbackModel(): array
    {
        $default = (string) config('ai.app.default_model', 'gpt-4o-mini');

        return [['id' => $default, 'label' => $default]];
    }
}
