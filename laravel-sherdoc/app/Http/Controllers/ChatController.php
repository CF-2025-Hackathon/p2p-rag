<?php

namespace App\Http\Controllers;

use App\Events\StreamUpdated;
use App\Jobs\HandleExpertiseJob;
use App\Jobs\ProcessStreamJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use LLPhant\Chat\OpenAIChat;
use LLPhant\Embeddings\DataReader\FileDataReader;
use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAI3SmallEmbeddingGenerator;
use LLPhant\Embeddings\VectorStores\Doctrine\DoctrineVectorStore;
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

class ChatController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        ProcessStreamJob::dispatch($request->input('messages'));

        return new JsonResponse([
            'ok' => true,
        ]);
    }

    public function query(Request $request): JsonResponse
    {
        // dump($request->all());

        $vector = $request->input('embedding.vector');

        // Log::error("req", $request->all());

        $vectorStore = new RedisVectorStore(Redis::connection()->client(), 'p2prag_data');
        $docs = $vectorStore->similaritySearch($vector, $request->input('embedding.match_count'));

        return new JsonResponse([
            'nodeId' => '1',
            'query' => [
                'queryId' => '1',
                'model' => 'nomic-embed-text',
                'vector' => $vector
            ],
            'answer' => [
                'documents' => collect($docs)->map(fn ($doc) => [
                    [
                        'title' => 'Title',
                        'content' => $doc->formattedContent ?: $doc->content,
                        'source' => $doc->sourceName,
                        'metadata' => [],
                    ],
                ]),
            ]
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        // $config = new OpenAIConfig();
        // $config->model = 'gpt-4o';
        // $config->apiKey = '';
        // $chat = new OpenAIChat($config);

        // $embeddingGenerator = new OpenAI3SmallEmbeddingGenerator($config);
        // $vectorStore = new RedisVectorStore(Redis::connection()->client(), 'p2prag_data');

        // $qa = new QuestionAnswering(
        //     $vectorStore,
        //     $embeddingGenerator,
        //     $chat,
        // );

        // $answer = $qa->answerQuestion('wat is dzjing?');

        // $config = new OllamaConfig();
        // $config->model = 'nomic-embed-text';
        // $config->url = 'http://ollama:11434/api/';

        // $embeddingGenerator = new OllamaEmbeddingGenerator($config);

        // $embeddingGenerator->embedText('test');

        // dd($_ENV);
        // dump(env('REDIS_HOST'));
        // dd(config('database.redis.default.host'));

        $this->updateCentroidVector('p2prag_data');

        return new JsonResponse([
            'ok' => true,
        ]);
    }

    public function train(Request $request): JsonResponse
    {
        $config = new OpenAIConfig();
        $config->model = 'gpt4o';
        $config->apiKey = config('services.openai.api_key');

        $dataReader = new FileDataReader(resource_path('test.txt'));
        $documents = $dataReader->getDocuments();

        $splittedDocuments = DocumentSplitter::splitDocuments($documents, 500);

        $embeddingGenerator = new OpenAI3SmallEmbeddingGenerator($config);
        $embeddedDocuments = $embeddingGenerator->embedDocuments($splittedDocuments);

        $vectorStore = new RedisVectorStore(Redis::connection()->client(), 'p2prag_data');
        $vectorStore->addDocuments($embeddedDocuments);

        return new JsonResponse([
            'ok' => true,
        ]);
    }

    public function handleExpertise(Request $request): JsonResponse
    {
        HandleExpertiseJob::dispatch($request->all());

        return new JsonResponse([
            'ok' => true,
        ]);
    }

    public function updateCentroidVector($index): void
    {
        $vectorStore = new RedisVectorStore(Redis::connection()->client(), 'p2prag_data');

        $pattern = "{$index}:*"; // Pattern to match keys
        $cursor = 0;
        $keys = [];

        // Step 1: Retrieve all relevant keys
        do {
            [$cursor, $batch] = Redis::scan($cursor, ['MATCH' => $pattern, 'COUNT' => 100]);
            $keys = array_merge($keys, $batch);
        } while ($cursor != 0);

        if (empty($keys)) {
            return; // No data found
        }

        // dd($keys);

        $results = $vectorStore->client->jsonmget(
            $keys, '$'
        );

        $vectors = collect($results)
            ->map(fn (string $json) => json_decode($json, true))
            ->map(fn (array $vectors) => Arr::get($vectors, '0.embedding'));

        $centroidVector = $this->calculateCentroidVector($vectors->toArray());

        Redis::set("expertise:centroid", json_encode($centroidVector));
        // Redis::command('JSON.set', ['expertise.centroid', '$', json_encode($centroidVector)]);

        $response = Http::contentType('application/json')
            ->post(rtrim(config('services.p2p.url'), '/') . '/expertise', [
                'embeddings' => [
                    [
                        'key' => 'centroid vector',
                        'expertise' => 'centroid vector',
                        'model' => 'nomic-embed-text',
                        'vector' => $centroidVector
                    ],
                ],
            ]);

        // dump($response->status());
        // dd($response->body());
    }

    private function calculateCentroidVector(array $vectors): array
    {
        $numVectors = count($vectors);
        $dimensions = count($vectors[0]);
        $sumVector = array_fill(0, $dimensions, 0);

        foreach ($vectors as $vector) {
            if (count($vector) !== $dimensions) {
                throw new InvalidArgumentException("All vectors must have the same dimensions.");
            }
            foreach ($vector as $i => $value) {
                $sumVector[$i] += $value;
            }
        }

        return array_map(fn($sum) => $sum / $numVectors, $sumVector);
    }
}
