<?php

namespace App\Ai;

/**
 * Hasil parsing response LLM ke schema internal.
 */
final class ChatResult
{
    public function __construct(
        public readonly string $status, // answered|clarification_required|no_evidence
        public readonly ?string $answer,
        public readonly ?string $clarificationQuestion,
        public readonly array $citationSourceIds = [],
    ) {}

    /**
     * Parse output LLM (JSON) menjadi ChatResult.
     * Bila gagal parse, kembalikan no_evidence (aman, tidak mengarang).
     */
    public static function parse(string $raw): self
    {
        $text = trim($raw);
        // buang code fence bila ada
        $text = preg_replace('/^```(?:json)?|```$/i', '', $text) ?? $text;
        $text = trim($text);

        $data = json_decode($text, true);

        if (! is_array($data)) {
            // Bila LLM tidak balas JSON, coba anggap teks sebagai answer sederhana.
            if ($text !== '') {
                return new self('answered', $text, null, []);
            }

            return new self('no_evidence', null, null, []);
        }

        $status = (string) ($data['status'] ?? 'answered');
        $status = in_array($status, ['answered', 'clarification_required', 'no_evidence'], true)
            ? $status
            : 'answered';

        $answer = isset($data['answer']) && is_string($data['answer']) && $data['answer'] !== ''
            ? $data['answer']
            : null;
        $clar = isset($data['clarificationQuestion']) && is_string($data['clarificationQuestion']) && $data['clarificationQuestion'] !== ''
            ? $data['clarificationQuestion']
            : null;
        $ids = [];
        foreach ((array) ($data['citationSourceIds'] ?? []) as $id) {
            if (is_string($id) && trim($id) !== '') {
                $ids[] = trim($id);
            }
        }

        // Konsistensi: clarification tanpa pertanyaan -> pakai default aman.
        if ($status === 'clarification_required' && $clar === null) {
            $clar = 'Wilayah dan periode mana yang Anda maksud?';
        }
        // no_evidence -> pastikan tidak ada answer mengarang.
        if ($status === 'no_evidence') {
            $answer = null;
            $ids = [];
        }

        return new self($status, $answer, $clar, $ids);
    }
}
