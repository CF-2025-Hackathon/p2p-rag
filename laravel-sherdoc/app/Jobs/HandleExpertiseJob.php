<?php

namespace App\Jobs;

use App\Events\StreamDone;
use App\Events\StreamUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;
use LLPhant\Chat\Enums\ChatRole;
use LLPhant\Chat\Message;
use LLPhant\Chat\OpenAIChat;
use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAI3SmallEmbeddingGenerator;
use LLPhant\Embeddings\VectorStores\Redis\RedisVectorStore;
use LLPhant\OllamaConfig;
use LLPhant\OpenAIConfig;
use LLPhant\Query\SemanticSearch\MultiQuery;
use LLPhant\Query\SemanticSearch\QueryTransformers\IdentityTransformer;
use LLPhant\Query\SemanticSearch\QuestionAnswering;
use LLPhant\Query\SemanticSearch\RetrievedDocsTransformers\ChunkDeduplicationTransformer;
use LLPhant\Query\SemanticSearch\RetrievedDocsTransformers\SequentialTransformer;
use LLPhant\Query\SemanticSearch\RetrievedDocsTransformers\SlidingWindowTransformer;
use Psr\Http\Message\StreamInterface;
use Pusher\PusherException;

class HandleExpertiseJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private array $data)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $vectorStore = new RedisVectorStore(Redis::connection()->client(), 'p2prag_expertise');

        $embeddings = Arr::get($this->data, 'embeddings');

        $i = 0;
        foreach ($embeddings as $embedding) {
            $doc = new Document();
            $doc->sourceType = 'p2p';
            $doc->sourceName = Arr::get($this->data, 'nodeId');
            $doc->content = Arr::get($this->data, 'nodeId');
            $doc->embedding = Arr::get($embedding, 'vector');
            $doc->chunkNumber = $i;
            $vectorStore->addDocument($doc);
            ++$i;
        }
    }
}
