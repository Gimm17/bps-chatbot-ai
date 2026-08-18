<?php

namespace App\Ai;

use App\Bps\BpsAgent;
use App\Rag\Citation;
use App\Rag\RetrieverInterface;
use App\Security\RequestId;

/**
 * Orkestrasi flow /api/chat per DOCS/03_API/02_INTERNAL_API_CONTRACT.md +
 * DOCS/05_AI/01_AI_RUNTIME_LOGIC.md.
 *
 * validate -> scope -> retrieve -> clarify -> prompt -> validate -> map citation -> safe response.
 */
final class ChatService
{
    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly RetrieverInterface $retriever,
        private readonly ScopeGuard $scopeGuard,
        private readonly PromptBuilder $promptBuilder,
        private readonly ?BpsAgent $bpsAgent = null,
    ) {}

    public function handle(string $message): ChatResponse
    {
        $requestId = RequestId::generate();

        // 1. Scope/intent guard.
        $scope = $this->scopeGuard->classify($message);

        if (! $scope->inScope) {
            return new ChatResponse($requestId, 'out_of_scope', answer: $this->outOfScopeAnswer());
        }

        // 2. Clarification bila parameter numerik kurang.
        if ($scope->intent === 'numeric_statistic' && $scope->missing !== []) {
            return new ChatResponse(
                $requestId,
                'clarification_required',
                clarificationQuestion: $this->clarificationQuestion($scope->missing),
            );
        }

        // 3. BPS WebAPI path (feature-flagged). Intent tanpa tool tetap fallback .md.
        if ($this->shouldUseBpsAgent()) {
            $result = $this->bpsAgent?->run($message, $scope->intent);
            if ($result !== null) {
                $citations = Citation::fromBpsSources(
                    $this->bpsAgent->collectedSources(),
                    $result->citationSourceIds,
                );

                return new ChatResponse(
                    $requestId,
                    $result->status,
                    answer: $result->answer,
                    clarificationQuestion: $result->clarificationQuestion,
                    citations: $citations,
                );
            }
        }

        // 4. Retrieve evidence fallback.
        $evidence = $this->retriever->retrieve($message, topK: 4);

        // 4. No-evidence bila retrieval kosong (jangan mengarang).
        if ($evidence === []) {
            return new ChatResponse(
                $requestId,
                'no_evidence',
                answer: 'Saya belum menemukan sumber BPS yang cukup untuk memastikan jawaban tersebut.',
            );
        }

        // 5. Build prompt + call provider.
        $instructions = $this->promptBuilder->buildInstructions($message, $evidence);
        $messages = $this->promptBuilder->buildMessages($message);
        try {
            $output = $this->provider->chat(new ChatProviderInput(
                messages: $messages,
                instructions: $instructions,
            ));
        } catch (\Throwable $e) {
            // Log internal tanpa secret; client dapat pesan aman.
            logger()->warning('bps-ai provider error', [
                'requestId' => $requestId,
                'error' => $e::class,
            ]);

            return new ChatResponse(
                $requestId,
                'provider_error',
                answer: 'Layanan AI sedang tidak tersedia. Silakan coba kembali beberapa saat lagi.',
            );
        }

        // 6. Parse + validate response.
        $result = ChatResult::parse($output->text);

        // 7. Map citation dari trusted registry (bukan URL output LLM).
        $citations = Citation::fromSources($evidence, $result->citationSourceIds);

        return new ChatResponse(
            $requestId,
            $result->status,
            answer: $result->answer,
            clarificationQuestion: $result->clarificationQuestion,
            citations: $citations,
        );
    }

    private function shouldUseBpsAgent(): bool
    {
        return $this->bpsAgent !== null
            && (bool) config('bps.enabled', false)
            && (string) config('bps.key', '') !== '';
    }

    private function outOfScopeAnswer(): string
    {
        return 'Saya difokuskan untuk membantu pertanyaan seputar BPS, statistik, publikasi, dan layanan BPS. Coba tanyakan: Apa itu inflasi?, Bagaimana mencari data penduduk?, atau Di mana saya bisa menemukan publikasi BPS?';
    }

    private function clarificationQuestion(array $missing): string
    {
        $need = [];
        if (in_array('geography', $missing, true)) {
            $need[] = 'wilayah (provinsi/kabupaten/kota)';
        }
        if (in_array('period', $missing, true)) {
            $need[] = 'periode/tahun';
        }
        if (in_array('indicator', $missing, true)) {
            $need[] = 'indikator';
        }

        $list = $need !== [] ? implode(', ', $need) : 'wilayah dan periode';

        return "Saya perlu sedikit informasi tambahan. Sebutkan {$list} yang Anda maksud.";
    }
}
