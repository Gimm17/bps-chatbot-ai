<?php

namespace App\Rag;

/**
 * Boundary retrieval agar nanti bisa diganti HybridRetriever tanpa mengubah UI/logik.
 *
 *     RetrieverInterface
 *         ├── DemoLexicalRetriever
 *         └── FutureHybridRetriever   (BM25 + embedding + reranker)
 */
interface RetrieverInterface
{
    /**
     * @return list<RetrievedSource>
     */
    public function retrieve(string $question, int $topK = 4, ?float $threshold = null): array;
}
