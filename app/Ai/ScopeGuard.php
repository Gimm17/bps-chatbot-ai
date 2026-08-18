<?php

namespace App\Ai;

use function Laravel\Ai\agent;

/**
 * Scope guard dua lapis per DOCS/05_AI/03_SCOPE_ROUTING.md:
 *  Layer 1: heuristics murah untuk obvious cases.
 *  Layer 2: LLM classifier untuk ambiguous cases.
 *
 * Jangan mengeluarkan request LLM mahal bila jelas out-of-scope dari heuristik.
 */
final class ScopeGuard
{
    /** Kata kunci in-scope kuat (Layer 1 positif). */
    private const INSCOPE_KEYWORDS = [
        'bps', 'statistik', 'data', 'inflasi', 'deflasi', 'pdrb', 'pdb',
        'sensus', 'survei', 'publikasi', 'metadata', 'metodologi', 'indikator',
        'penduduk', 'kependudukan', 'ekspor', 'impor', 'kemiskinan', 'ipm',
        'ketenagakerjaan', 'pengangguran', 'kbli', 'kbkm', 'ihk', 'pertumbuhan',
        'ekonomi', 'triwulan', 'rupiah', 'wilayah', 'provinsi', 'kabupaten',
        'kota', 'desa', 'layanan', 'publikasi', 'navigasi', 'perioda', 'periode',
    ];

    /** Penanda jelas out-of-scope (Layer 1 negatif). */
    private const OUTSCOPE_KEYWORDS = [
        'puisi', 'pantun', 'cerita', 'lagu', 'lirik', 'resep masakan', 'diet',
        'coding', 'kode program', 'debug', 'tutorial', 'game', 'permainan',
        'jodoh', 'ramalan', 'zodiak', 'horoskop', 'cinta', 'curhat', 'film',
        'novel', 'biografi selebriti', 'olahraga', 'bola', 'politik', 'agama',
        'gaya rambut', 'skincare', 'rekomendasi beli', 'beli', 'harga hp',
    ];

    public function __construct(
        private readonly bool $useLlmLayer = true,
    ) {}

    public function classify(string $question): ScopeDecision
    {
        $q = mb_strtolower(trim($question));

        // Layer 1a: out-of-scope kuat -> langsung tolak tanpa LLM.
        foreach (self::OUTSCOPE_KEYWORDS as $kw) {
            if (str_contains($q, $kw)) {
                return ScopeDecision::outOfScope();
            }
        }

        // Layer 1b: in-scope kuat -> tentukan intent heuristik + cek parameter.
        $intent = $this->heuristicIntent($q);
        if ($intent !== null) {
            return $this->finalize($intent, $q);
        }

        // Layer 2: ambiguous -> LLM classifier.
        if ($this->useLlmLayer && config('ai.providers.limitrouter.key') && ! str_starts_with((string) config('ai.providers.limitrouter.key'), 'sk-lr-REPLACE')) {
            $decision = $this->llmClassify($question);
            if ($decision !== null) {
                return $decision;
            }
        }

        // Bila LLM tidak tersedia/gagal: default ke in-scope paling aman
        // (definition) agar demo tetap menjawab, bukan menolak sembarangan.
        return $this->finalize('definition', $q);
    }

    private function heuristicIntent(string $q): ?string
    {
        $hasScope = false;
        foreach (self::INSCOPE_KEYWORDS as $kw) {
            if (str_contains($q, $kw)) {
                $hasScope = true;
                break;
            }
        }
        if (! $hasScope) {
            return null;
        }

        if (preg_match('/(apa itu|apa maksud|apa arti|jelaskan|definisi|pengertian|konsep)\b/', $q)) {
            return 'definition';
        }
        if (preg_match('/(berapa|jumlah|angka|nilai|data [0-9]|tingkat|persentase|rasio)\b/', $q)) {
            return 'numeric_statistic';
        }
        if (preg_match('/(publikasi|cari publikasi|unduh|download|rilis)\b/', $q)) {
            return 'publication';
        }
        if (preg_match('/(metodologi|metadata|cara hitung|cara menghitung|klasifikasi|kbli)\b/', $q)) {
            return 'metadata_methodology';
        }
        if (preg_match('/(layanan|cara (mengakses|mendaftar|memesan)|p2s|bantuan)\b/', $q)) {
            return 'bps_service';
        }
        if (preg_match('/(di mana|dimana|cara mencari|navigasi|situs|website|menu)\b/', $q)) {
            return 'navigation';
        }

        return 'definition'; // in-scope tapi intent generik
    }

    /**
     * Cek parameter wajib untuk numeric_statistic; bila kurang -> missing.
     */
    private function finalize(string $intent, string $q): ScopeDecision
    {
        $missing = [];
        if ($intent === 'numeric_statistic') {
            if (! preg_match('/(provinsi|kabupaten|kota|indonesia|desa|kecamatan|wilayah|daerah)\b/', $q)) {
                $missing[] = 'geography';
            }
            if (! preg_match('/(tahun|periode|triwulan|bulanan|bulan [0-9]|[0-9]{4})\b/', $q)) {
                $missing[] = 'period';
            }
        }

        return ScopeDecision::inScope($intent, $missing);
    }

    private function llmClassify(string $question): ?ScopeDecision
    {
        try {
            $instructions = <<<'INSTR'
Klasifikasikan pertanyaan pengguna untuk BPS AI Assistant.
Balas JSON valid saja (tanpa code fence):
{"inScope": true|false, "intent": "definition|numeric_statistic|publication|metadata_methodology|bps_service|navigation|out_of_scope", "missing": []}
Field "missing" hanya diisi string dari: "indicator","geography","period" bila parameter wajib untuk pertanyaan numerik belum disebut. Bila bukan numerik, "missing": [].
inScope=false hanya bila jelas di luar topik BPS/statistik/layanan statistik.
INSTR;

            $agent = agent(instructions: $instructions, messages: []);
            $resp = $agent->prompt($question, provider: 'limitrouter', model: (string) config('ai.app.default_model', 'gpt-4o-mini'), timeout: 20);
            $text = trim((string) $resp->text);
            $text = preg_replace('/^```(?:json)?|```$/i', '', $text) ?? $text;
            $data = json_decode($text, true);
            if (! is_array($data)) {
                return null;
            }

            $inScope = (bool) ($data['inScope'] ?? true);
            $intent = (string) ($data['intent'] ?? 'definition');
            $missing = array_values(array_filter((array) ($data['missing'] ?? []), 'is_string'));

            return $inScope
                ? ScopeDecision::inScope($intent, $missing)
                : ScopeDecision::outOfScope();
        } catch (\Throwable) {
            return null;
        }
    }
}
