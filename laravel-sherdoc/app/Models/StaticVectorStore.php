<?php

declare(strict_types=1);

namespace App\Models;

use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\VectorStores\VectorStoreBase;

class StaticVectorStore extends VectorStoreBase
{
    public function __construct(private array $documents)
    {
    }

    public function addDocument(Document $document): void
    {
    }

    public function addDocuments(array $documents): void
    {
    }

    public function similaritySearch(array $embedding, int $k = 4, array $additionalArguments = []): array
    {
        return $this->documents;
    }
}
