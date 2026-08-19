<?php

namespace App\Ai;

use App\Bps\BpsAgent;
use App\Rag\Citation;
use App\Rag\RetrieverInterface;
use App\Security\RequestId;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;

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

    /** Batasi history ke N turn terakhir agar context tidak bloat. */
    private const HISTORY_MAX_TURNS = 6;

    public function handle(string $message, string $conversationId = ''): ChatResponse
    {
        $requestId = RequestId::generate();

        // 0. Ambil history turn sebelumnya (server-side, per conversationId).
        $history = $this->historyFor($conversationId);

        // 1. Scope/intent guard. Pakai history untuk melanjutkan clarification
        //    tanpa menanyakan ulang parameter yang sudah disebut bubble sebelumnya.
        $scope = $history !== []
            ? $this->scopeGuard->classifyWithHistory($message, $history)
            : $this->scopeGuard->classify($message);

        if (! $scope->inScope) {
            return new ChatResponse($requestId, 'out_of_scope', answer: $this->outOfScopeAnswer());
        }

        // 1b. Sapaan murni -> balas ramah tanpa provider/retriever (cepat, hemat quota).
        if ($scope->intent === 'greeting') {
            $resp = new ChatResponse($requestId, 'answered', answer: $this->greetingAnswer());
            $this->remember($conversationId, $message, (string) $resp->answer);

            return $resp;
        }

        // 2. Clarification bila parameter numerik kurang.
        if ($scope->intent === 'numeric_statistic' && $scope->missing !== []) {
            $resp = new ChatResponse(
                $requestId,
                'clarification_required',
                clarificationQuestion: $this->clarificationQuestion($scope->missing),
            );
            // Simpan turn ini agar bubble jawaban user melengkapi (tidak tanya ulang).
            $this->remember($conversationId, $message, (string) ($resp->clarificationQuestion ?? ''));

            return $resp;
        }

        // 3. BPS WebAPI path (feature-flagged). Intent tanpa tool tetap fallback .md.
        if ($this->shouldUseBpsAgent()) {
            // Untuk follow-up singkat ("tahun 2023") yang melengkapi clarification
            // numerik, bangun satu pertanyaan efektif yang menggabungkan history
            // agar agent melihat konteks tanpa membawa topik/jawaban bubble lain
            // (mencegah context bleed). Pertanyaan mandiri tidak digabung.
            $question = $this->effectiveQuestion($message, $history, $scope);
            $result = $this->bpsAgent?->run($question, $scope->intent);
            if ($result !== null) {
                $citations = Citation::fromBpsSources(
                    $this->bpsAgent->collectedSources(),
                    $result->citationSourceIds,
                );

                $resp = new ChatResponse(
                    $requestId,
                    $result->status,
                    answer: $result->answer,
                    clarificationQuestion: $result->clarificationQuestion,
                    citations: $citations,
                );
                $this->remember($conversationId, $message, (string) ($resp->answer ?? $resp->clarificationQuestion ?? ''));

                return $resp;
            }
        }

        // 4. Retrieve evidence fallback.
        $evidence = $this->retriever->retrieve($message, topK: 4);

        // 4. No-evidence bila retrieval kosong (jangan mengarang).
        if ($evidence === []) {
            $resp = new ChatResponse(
                $requestId,
                'no_evidence',
                answer: 'Saya belum menemukan sumber BPS yang cukup untuk memastikan jawaban tersebut.',
            );
            $this->remember($conversationId, $message, (string) $resp->answer);

            return $resp;
        }

        // 5. Build prompt + call provider. Sertakan history sebagai context turn sebelumnya.
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

            $resp = new ChatResponse(
                $requestId,
                'provider_error',
                answer: 'Layanan AI sedang tidak tersedia. Silakan coba kembali beberapa saat lagi.',
            );
            $this->remember($conversationId, $message, (string) $resp->answer);

            return $resp;
        }

        // 6. Parse + validate response.
        $result = ChatResult::parse($output->text);

        // 7. Map citation dari trusted registry (bukan URL output LLM).
        $citations = Citation::fromSources($evidence, $result->citationSourceIds);

        $resp = new ChatResponse(
            $requestId,
            $result->status,
            answer: $result->answer,
            clarificationQuestion: $result->clarificationQuestion,
            citations: $citations,
        );
        $this->remember($conversationId, $message, (string) ($resp->answer ?? $resp->clarificationQuestion ?? ''));

        return $resp;
    }

    /**
     * Ambil daftar pesan user sebelumnya dalam sesi ini (terbaru terakhir).
     * Server-side store per conversationId; tidak bergantung pada frontend.
     *
     * @return list<string>
     */
    private function historyFor(string $conversationId): array
    {
        if ($conversationId === '') {
            return [];
        }
        $history = Cache::get($this->historyKey($conversationId), []);
        if (! is_array($history)) {
            $history = [];
        }
        $history = array_values(array_filter($history, 'is_string'));

        return $history;
    }

    /** Simpan turn (pesan user) ke history sesi; dipotong ke HISTORY_MAX_TURNS. */
    private function remember(string $conversationId, string $message, string $answer): void
    {
        if ($conversationId === '') {
            return;
        }
        $history = $this->historyFor($conversationId);
        $history[] = $message;
        // Simpan hanya pesan user untuk classification; jawaban bot tidak dipakai
        // sebagai parameter geography/period (menghindari false positive).
        $history = array_slice($history, -self::HISTORY_MAX_TURNS);
        Cache::put($this->historyKey($conversationId), $history, now()->addHours(24));
    }

    private function historyKey(string $conversationId): string
    {
        return 'bpsconv:'.sha1($conversationId);
    }

    /**
     * Bangun pertanyaan efektif untuk agent. Hanya gabungkan history bila pesan
     * sekarang adalah follow-up singkat untuk numeric_statistic (mis. "tahun 2023"
     * atau "jumlah penduduk" setelah clarification); jika history sudah memuat
     * konteks wilayah, hasilnya adalah satu pertanyaan lengkap, bukan daftar turn.
     * Ini mencegah context bleed — agent tidak lagi melihat bubble inflasi
     * sebelumnya sebagai user-turn terpisah yang harus dilengkapi.
     */
    private function effectiveQuestion(string $message, array $history, \App\Ai\ScopeDecision $scope): string
    {
        if ($history === [] || $scope->intent !== 'numeric_statistic') {
            return $message;
        }
        $trimmed = trim($message);
        $isShortFollowup = mb_strlen($trimmed) <= 40 || preg_match('/^(tahun|periode|jumlah|berapa|terbaru|terkini)\b/i', $trimmed);
        if (! $isShortFollowup) {
            return $message;
        }
        $previous = end($history) ?: '';

        return $previous !== '' ? "{$previous} {$trimmed}" : $message;
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

    private function greetingAnswer(): string
    {
        return 'Halo! Saya BPS AI Assistant, siap membantu pertanyaan seputar data dan statistik BPS — misalnya: "Apa itu inflasi?", "Berapa jumlah penduduk Jawa Barat tahun 2023?", atau "Publikasi apa saja dari BPS?". Apa yang ingin Anda ketahui?';
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
