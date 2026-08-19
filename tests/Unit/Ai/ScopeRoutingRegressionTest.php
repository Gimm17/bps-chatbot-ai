<?php

namespace Tests\Unit\Ai;

use App\Ai\ScopeGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression untuk 3 bug produksi 2026-08-19:
 *  1. Sapaan (halo/hai) salah diklasifikasi out_of_scope.
 *  2. Numeric dengan nama provinsi aktual + "terbaru" tetap minta klarifikasi geography/period.
 *  3. Intent definition kini punya tool BPS (diuji di level registry/service).
 *
 * Fokus layer heuristik ScopeGuard (useLlmLayer:false) agar deterministik.
 */
class ScopeRoutingRegressionTest extends TestCase
{
    public function test_greeting_is_in_scope_and_not_out_of_scope(): void
    {
        $guard = new ScopeGuard(useLlmLayer: false);

        foreach (['halo', 'Halo!', 'hai', 'Hai, apa kabar?', 'hello', 'selamat pagi', 'assalamualaikum'] as $msg) {
            $decision = $guard->classify($msg);
            $this->assertTrue($decision->inScope, "Greeting should be in-scope: {$msg}");
            $this->assertNotSame('out_of_scope', $decision->intent, "Greeting must not be out_of_scope: {$msg}");
        }
    }

    public function test_numeric_with_real_province_name_does_not_require_geography(): void
    {
        $guard = new ScopeGuard(useLlmLayer: false);

        $decision = $guard->classify('Berapa jumlah penduduk Sulawesi Tengah terbaru?');
        $this->assertSame('numeric_statistic', $decision->intent);
        $this->assertNotContains('geography', $decision->missing, 'Sulawesi Tengah adalah wilayah valid');
    }

    public function test_numeric_with_terbaru_does_not_require_period(): void
    {
        $guard = new ScopeGuard(useLlmLayer: false);

        $decision = $guard->classify('Berapa jumlah penduduk Jawa Barat terbaru?');
        $this->assertSame('numeric_statistic', $decision->intent);
        $this->assertNotContains('period', $decision->missing, '"terbaru" = latest period');
        $this->assertNotContains('geography', $decision->missing, 'Jawa Barat = wilayah valid');
    }

    #[DataProvider('provinceProvider')]
    public function test_provinces_recognized_as_geography(string $province): void
    {
        $guard = new ScopeGuard(useLlmLayer: false);
        $decision = $guard->classify("Berapa jumlah penduduk {$province} tahun 2023?");
        $this->assertNotContains('geography', $decision->missing, "Province not recognized: {$province}");
    }

    public static function provinceProvider(): array
    {
        return [
            'Jawa Barat' => ['Jawa Barat'],
            'Sulawesi Tengah' => ['Sulawesi Tengah'],
            'DKI Jakarta' => ['DKI Jakarta'],
            'Papua Pegunungan' => ['Papua Pegunungan'],
            'Nusa Tenggara Timur' => ['Nusa Tenggara Timur'],
            'Aceh' => ['Aceh'],
            'Bali' => ['Bali'],
        ];
    }

    public function test_generic_placeholder_still_requires_geography(): void
    {
        // "di sini" bukan wilayah konkret -> tetap minta geography (jangan regress).
        $guard = new ScopeGuard(useLlmLayer: false);
        $decision = $guard->classify('Berapa jumlah penduduk di sini?');
        $this->assertContains('geography', $decision->missing);
    }

    public function test_numeric_without_period_and_without_latest_keyword_requires_period(): void
    {
        // Jangan regress: tanpa tahun & tanpa "terbaru" -> tetap perlu period.
        $guard = new ScopeGuard(useLlmLayer: false);
        $decision = $guard->classify('Berapa jumlah penduduk Jawa Barat?');
        $this->assertContains('period', $decision->missing);
    }
}
