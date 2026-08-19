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

    /**
     * Pola sapaan. Dicocokkan SEBELUM out-of-scope check karena sapaan murni
     * (tanpa topik lain) tidak boleh ditolak. Short-circuit ke intent `greeting`.
     * Diutamakan word-boundary agar "halo" tidak false-positive di tengah kata.
     */
    private const GREETING_PATTERNS = [
        '/^(\s)*(halo|hallo|hai|hi|hello|hey|assalamualaiku?m+|salam)\b/i',
        '/^(\s)*(selamat|met)\s+(pagi|siang|sore|malam|malam hari)\b/i',
        '/^(\s)*(apa|bagaimana)\s+kabar\b/i',
    ];

    /**
     * Nama 38 provinsi Indonesia (termasuk DOB 2022-2024) lowercase.
     * Dipakai mendeteksi geography pada numeric_statistic bila kata generik
     * (provinsi/kabupaten/kota) tidak muncul tetapi nama wilayah konkret ada.
     */
    private const PROVINCE_PATTERNS = [
        'aceh', 'sumatera utara', 'sumatera barat', 'riau', 'jambi',
        'sumatera selatan', 'bengkulu', 'lampung', 'kepulauan bangka belitung',
        'kepulauan riau', 'dki jakarta', 'jakarta', 'jawa barat', 'jawa tengah',
        'di yogyakarta', 'yogyakarta', 'jawa timur', 'banten', 'bali',
        'nusa tenggara barat', 'nusa tenggara timur', 'kalimantan barat',
        'kalimantan tengah', 'kalimantan selatan', 'kalimantan timur',
        'kalimantan utara', 'sulawesi utara', 'gorontalo', 'sulawesi tengah',
        'sulawesi selatan', 'sulawesi tenggara', 'sulawesi barat', 'maluku',
        'maluku utara', 'papua', 'papua barat', 'papua selatan', 'papua tengah',
        'papua pegunungan', 'papua barat daya',
    ];

    /** Penanda periode "terbaru" — dianggap period sudah cukup (latest). */
    private const LATEST_PERIOD_KEYWORDS = [
        'terbaru', 'terkini', 'terakhir', 'latest', 'sekarang', 'tahun ini',
        'kini', 'saat ini', 'paling baru', 'paling akhir',
    ];

    public function __construct(
        private readonly bool $useLlmLayer = true,
    ) {}

    public function classify(string $question): ScopeDecision
    {
        $q = mb_strtolower(trim($question));

        // Layer 0: sapaan murni -> balas ramah, jangan sampai ke LLM/out-of-scope.
        if ($this->isGreeting($q)) {
            return ScopeDecision::inScope('greeting', []);
        }

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

    /**
     * Classify dengan konteks multi-turn: intent diambil dari pesan sekarang,
     * tetapi deteksi geography/period digabung dari pesan sekarang + history.
     * Dipakai untuk melanjutkan clarification tanpa menanyakan ulang parameter
     * yang sudah disebut di bubble sebelumnya.
     *
     * @param  list<string>  $history  pesan-pesan sebelumnya dalam sesi (terbaru terakhir)
     */
    public function classifyWithHistory(string $question, array $history): ScopeDecision
    {
        $q = mb_strtolower(trim($question));
        $decision = $this->classify($question);

        $combined = $q.' '.mb_strtolower(implode(' ', array_map('strval', $history)));

        // Warisi intent numeric_statistic bila pesan sekarang ambigu (mis. jawaban
        // singkat "tahun 2023" / "jumlah penduduk") tetapi gabungan dengan history
        // membentuk query numeric lengkap (indikator + wilayah/periode).
        $intent = $decision->intent;
        if ($intent !== 'numeric_statistic'
            && preg_match('/(berapa|jumlah|angka|nilai|tingkat|persentase|rasio)\b/', $combined)
        ) {
            $intent = 'numeric_statistic';
        }

        // Hanya gabung parameter untuk numeric_statistic; intent lain tidak
        // bergantung pada geography/period.
        if ($intent !== 'numeric_statistic') {
            return $decision;
        }

        $missing = [];
        if (! $this->hasGeography($combined)) {
            $missing[] = 'geography';
        }
        if (! $this->hasPeriod($combined)) {
            $missing[] = 'period';
        }

        return ScopeDecision::inScope('numeric_statistic', $missing);
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
            if (! $this->hasGeography($q)) {
                $missing[] = 'geography';
            }
            if (! $this->hasPeriod($q)) {
                $missing[] = 'period';
            }
        }

        return ScopeDecision::inScope($intent, $missing);
    }

    /**
     * Geography hadir bila ada kata generik wilayah ATAU nama provinsi konkret
     * ATAU konteks nasional. "di sini"/placeholder tidak dianggap geography.
     */
    private function hasGeography(string $q): bool
    {
        if (preg_match('/(provinsi|kabupaten|kota|indonesia|nasional|desa|kecamatan|kepulauan|wilayah|daerah)\b/', $q)) {
            return true;
        }

        foreach (self::PROVINCE_PATTERNS as $prov) {
            // Word-boundary match: toleran terhadap tanda baca di akhir
            // (mis. "sulawesi tengah?" / "sulawesi tengah,"), bukan spasi-presisi.
            if (preg_match('/(?:^|\W)'.preg_quote($prov, '\/').'(?:\W|$)/', $q)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Period hadir bila ada tahun/periode/triwulan/bulan ATAU kata kunci
     * "terbaru/terkini/sekarang" yang menyatakan latest period.
     */
    private function hasPeriod(string $q): bool
    {
        if (preg_match('/(tahun|periode|perioda|triwulan|bulanan|semester|bulan [0-9]|[0-9]{4})\b/', $q)) {
            return true;
        }

        foreach (self::LATEST_PERIOD_KEYWORDS as $kw) {
            if (str_contains($q, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apakah pesan murni sapaan? Cocok pola greeting dan tidak mengandung
     * kata in-scope (agar "halo, tanya inflasi" tetap diproses normal).
     */
    private function isGreeting(string $q): bool
    {
        // Jika ada kata in-scope kuat, bukan sapaan murni -> proses normal.
        foreach (self::INSCOPE_KEYWORDS as $kw) {
            if (str_contains($q, $kw)) {
                return false;
            }
        }

        foreach (self::GREETING_PATTERNS as $pattern) {
            if (preg_match($pattern, $q)) {
                return true;
            }
        }

        return false;
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
