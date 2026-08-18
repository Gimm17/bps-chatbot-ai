<?php

namespace App\Rag;

/**
 * Retriever v0 — lexical retrieval sederhana.
 *
 * Algoritma (per DOCS/06_RAG/03_RETRIEVER_V0_SPEC.md):
 *  1. lowercase normalize
 *  2. tokenize
 *  3. minimal stopword
 *  4. title hit weight > body hit
 *  5. phrase bonus
 *  6. top 3–5
 *  7. minimum threshold -> bila semua skor buruk, return [] (no_evidence)
 *
 * ponytail: TF sederhana tanpa IDF. Upgrade ke BM25 saat production RAG.
 */
final class DemoLexicalRetriever implements RetrieverInterface
{
    /** Skor minimum agar dokumen dianggap relevan. */
    private const DEFAULT_THRESHOLD = 0.35;

    /** Bobot hit pada title vs body. */
    private const TITLE_WEIGHT = 3.0;

    private const BODY_WEIGHT = 1.0;

    /** Bonus bila seluruh query muncul sebagai frase di dokumen. */
    private const PHRASE_BONUS = 2.0;

    private const STOPWORDS = [
        'yang', 'dan', 'di', 'ke', 'dari', 'untuk', 'pada', 'adalah', 'apa', 'itu',
        'ini', 'dengan', 'atau', 'akan', 'bisa', 'saya', 'anda', 'kita', 'mereka',
        'tentang', 'bagaimana', 'cara', 'dimana', 'mana', 'siapa', 'kapan', 'berapa',
        'juga', 'tidak', 'nya', 'la', 'para', 'oleh', 'agar', 'sudah', 'sedang',
        'the', 'a', 'an', 'of', 'to', 'in', 'is', 'are', 'what', 'how', 'where',
    ];

    /**
     * @param  list<KnowledgeDoc>  $docs
     */
    public function __construct(
        private readonly array $docs,
    ) {}

    public function retrieve(string $question, int $topK = 4, ?float $threshold = null): array
    {
        $threshold ??= self::DEFAULT_THRESHOLD;
        $qTokens = $this->tokenize($question);

        if ($qTokens === []) {
            return [];
        }

        $qPhrase = implode(' ', $qTokens);
        $scored = [];

        foreach ($this->docs as $doc) {
            $titleTokens = $this->tokenize($doc->title);
            $bodyTokens = $this->tokenize($doc->content);

            $score = 0.0;
            foreach ($qTokens as $qt) {
                $titleHits = $this->countHits($titleTokens, $qt);
                $bodyHits = $this->countHits($bodyTokens, $qt);
                if ($titleHits === 0 && $bodyHits === 0) {
                    continue;
                }
                $score += $titleHits * self::TITLE_WEIGHT;
                $score += $bodyHits * self::BODY_WEIGHT;
            }

            // Normalisasi kasar terhadap panjang query.
            if ($score > 0) {
                $score = $score / count($qTokens);
            }

            // Phrase bonus.
            if ($qPhrase !== '' && str_contains(strtolower($doc->title.' '.$doc->content), $qPhrase)) {
                $score += self::PHRASE_BONUS;
            }

            if ($score < $threshold) {
                continue;
            }

            $scored[] = new RetrievedSource(
                sourceId: $doc->sourceId,
                title: $doc->title,
                sourceUrl: $doc->sourceUrl,
                content: $doc->content,
                score: $score,
            );
        }

        usort($scored, fn (RetrievedSource $a, RetrievedSource $b) => $b->score <=> $a->score);

        return array_slice($scored, 0, max(1, $topK));
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $text = strtolower($text);
        // pisah pada non-alfanumerik
        $parts = preg_split('/[^a-z0-9]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $out = [];
        foreach ($parts as $p) {
            if (in_array($p, self::STOPWORDS, true)) {
                continue;
            }
            $out[] = $p;
        }

        return $out;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function countHits(array $tokens, string $term): int
    {
        $count = 0;
        foreach ($tokens as $t) {
            if ($t === $term) {
                $count++;
            }
        }

        return $count;
    }
}
