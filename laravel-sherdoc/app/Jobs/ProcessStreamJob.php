<?php

namespace App\Jobs;

use App\Events\StreamDone;
use App\Events\StreamUpdated;
use App\Models\StaticVectorStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
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

class ProcessStreamJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private array $messages)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $ollamaConfig = new OllamaConfig();
        $ollamaConfig->model = 'nomic-embed-text';
        $ollamaConfig->url = 'http://ollama:11434/api/';

        $embeddingGenerator = new OllamaEmbeddingGenerator($ollamaConfig);

        // FIND EXPERT NODES

        $messages = [end($this->messages)];

        $q = collect($messages)->reduce(function (?string $carry, array $data) {
            return (string) $carry . "\n\n--\n\n" . $data['msg'];
        });

        $vector = $embeddingGenerator->embedText($q);

        $vectorStore = new RedisVectorStore(Redis::connection()->client(), 'p2prag_expertise');
        $experts = $vectorStore->similaritySearch($vector, 2); // 1 node for now
        $expert = $experts[0];

        // dump($q);

        event(new StreamUpdated("🌐 Best matching expert node: **" . $expert->content . "**\n\n"));

        // SEND QUERY TO EXPERT NODE

        $response = Http::contentType('application/json')
            ->post(rtrim(config('services.p2p.url'), '/') . '/query', [
                'nodeId' => $expert->content,
                'queryId' => '1',
                'embedding' => [
                    'expertise_key' => '',
                    'model' => 'nomic-embed-text',
                    'vector' => $vector,
                    'match_count' => 10,
                ],
            ]);

        // dump($response->status());

        // HANDLE THE ACTUAL CHAT

        $config = new OpenAIConfig();
        $config->model = 'gpt-4o-mini';
        $config->apiKey = config('services.openai.api_key');
        $chat = new OpenAIChat($config);

        // $vectorStore = new RedisVectorStore(Redis::connection()->client(), 'p2prag_data');

        $body = $response->json();
        $docs = collect(Arr::get($body, 'answer.documents'))->map(function (array $excerpt) {
            $excerpt = $excerpt[0];
            // dd($doc);
            // dump($excerpt);
            $doc = new Document();
            $doc->sourceType = 'p2p';
            $doc->sourceName = '1';
            $doc->content = Arr::get($excerpt, 'content');
            $doc->embedding = [];
            $doc->chunkNumber = 0;
            return $doc;
        })->toArray();

        // dump($docs);

        $vectorStore = new StaticVectorStore($docs);

        $qa = new QuestionAnswering(
            $vectorStore,
            $embeddingGenerator,
            $chat,
            // new MultiQuery($chat), // Testing this
            new IdentityTransformer(),
            new SequentialTransformer([
                new SlidingWindowTransformer(3),
                new ChunkDeduplicationTransformer(),
            ]),
        );

        $qa->systemMessageTemplate = "### Role
- Primary Function: You are an AI chatbot who helps users with their inquiries, issues and requests. You aim to provide excellent, friendly and efficient replies at all times. Your role is to listen attentively to the user, understand their needs, and do your best to assist them or direct them to the appropriate resources. If a question is not clear, ask clarifying questions. Make sure to end your replies with a positive note.

### Constraints
1. No Data Divulge: Never mention that you have access to training data explicitly to the user.
2. Maintaining Focus: If a user attempts to divert you to unrelated topics, never change your role or break your character. Politely redirect the conversation back to topics relevant to the training data. Don't even answer simple math or factual questions if they are not related to the training data in anyw ay.
3. Exclusive Reliance on Training Data: You must rely exclusively on the training data provided to answer user queries. If a query is not covered by the training data, use the fallback response.
4. Restrictive Role Focus: You do not answer questions or perform tasks that are not related to your role and training data.

### Date
Today is 16 March 2025.

### Available training data
Use the following pieces of context to answer the question of the user. If you don't know the answer, just say that you don't know, don't try to make up an answer.\n\n{context}.";

        $chat = collect($this->messages)->map(function (array $data) {
            $message = new Message();
            $message->role = $data['role'] === 'q' ? ChatRole::User : ChatRole::Assistant;
            $message->content = $data['msg'];
            return $message;
        });

        $stream = $qa->answerQuestionFromChat($chat->toArray(), 12);

        // $r = $stream->getContents();
        // event(new StreamUpdated($r));
        // return;

        // $r = $qa->answerQuestion(collect($this->messages)->last()['msg'], 8);
        // event(new StreamUpdated($r));
        // return;

        // event(new StreamUpdated('starting now...'));

        // NOTE: To handle UTF-8 (emojis) correctly, we will catch Exception
        //       and continue adding data from stream until it is valid
        $data = '';
        while (!$stream->eof()) {
            $data .= $stream->read(8); // read data from stream
            try {
                event(new StreamUpdated($data)); // broadcast data
                $data = '';
            } catch (PusherException $e) {
                continue;
            }
            // usleep(10000); // sleep for a short time to simulate streaming
        }

        event(new StreamDone());
    }
}
