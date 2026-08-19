<?php

namespace Tests\Unit\Ai;

use App\Ai\ChatService;
use App\Ai\ScopeGuard;
use Tests\TestCase;

/**
 * Multi-turn context: setiap bubble dalam satu sesi (conversationId) harus
 * mengingat bubble sebelumnya. Contoh dari produksi 19 Aug 2026:
 *   "berapa jumlah penduduk sulawesi tengah" (clarification: periode?)
 *   "tahun 2023" (harus LANJUTKAN, bukan minta wilayah lagi)
 *
 * Implementasi: server-side conversation store per conversationId (cache),
 * history dipakai untuk classification (akumulasi parameter) + context ke agent.
 */
class MultiturnContextTest extends TestCase
{
    public function test_classify_followup_period_uses_province_from_history(): void
    {
        $guard = new ScopeGuard(useLlmLayer: false);

        // Tanpa history: "tahun 2023" sendirian ambigu (bukan numeric statistik lengkap).
        $alone = $guard->classify('tahun 2023');

        // Dengan history "berapa jumlah penduduk Sulawesi Tengah": geography ada,
        // period ada di pesan sekarang -> numeric_statistic tanpa missing.
        $withHistory = $guard->classifyWithHistory('tahun 2023', ['berapa jumlah penduduk Sulawesi Tengah']);
        $this->assertSame('numeric_statistic', $withHistory->intent);
        $this->assertNotContains('geography', $withHistory->missing);
        $this->assertNotContains('period', $withHistory->missing);
    }

    public function test_classify_with_history_combines_province_and_year(): void
    {
        $guard = new ScopeGuard(useLlmLayer: false);

        // History punya periode, pesan sekarang punya indikator+geography.
        $d = $guard->classifyWithHistory('jumlah penduduk Sulawesi Tengah', ['tahun 2023']);
        $this->assertSame('numeric_statistic', $d->intent);
        $this->assertNotContains('geography', $d->missing);
        $this->assertNotContains('period', $d->missing);
    }

    public function test_history_does_not_mask_missing_when_truly_absent(): void
    {
        // Guard: history tanpa wilayah, pesan tanpa wilayah -> tetap missing geography.
        $guard = new ScopeGuard(useLlmLayer: false);
        $d = $guard->classifyWithHistory('berapa jumlah penduduk?', ['tahun 2023']);
        $this->assertContains('geography', $d->missing);
    }

    public function test_chatservice_followup_not_clarification_again(): void
    {
        $svc = $this->app->make(ChatService::class);
        $conv = 'conv-test-'.uniqid();

        $r1 = $svc->handle('berapa jumlah penduduk Sulawesi Tengah', $conv);
        $this->assertSame('clarification_required', $r1->status);

        // Turn 2: jawaban tahun melengkapi query -> BUKAN clarification lagi.
        $r2 = $svc->handle('tahun 2023', $conv);
        $this->assertNotSame('clarification_required', $r2->status);
    }

    public function test_chatservice_conversations_isolated(): void
    {
        $svc = $this->app->make(ChatService::class);
        $a = 'conv-A-'.uniqid();
        $b = 'conv-B-'.uniqid();

        $svc->handle('berapa jumlah penduduk Sulawesi Tengah', $a);
        $rb = $svc->handle('berapa jumlah penduduk?', $b);
        $this->assertSame('clarification_required', $rb->status);
        $this->assertStringContainsString('wilayah', (string) $rb->clarificationQuestion);
    }
}
