<?php

namespace App\Jobs;

use App\Events\CrawlDone;
use App\Events\CrawlUpdated;
use App\Events\ScrapeDone;
use App\Events\UrlScraped;
use App\Http\Controllers\ChatController;
use App\Spiders\CallbackItemProcessor;
use App\Spiders\ContentGrabber;
use App\Spiders\ContentGrabberM;
use App\Spiders\ContentGrabberX;
use App\Spiders\LinkFinder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use LLPhant\Embeddings\DataReader\FileDataReader;
use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;
use LLPhant\Embeddings\EmbeddingFormatter\EmbeddingFormatter;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAI3SmallEmbeddingGenerator;
use LLPhant\Embeddings\VectorStores\Redis\RedisVectorStore;
use LLPhant\OllamaConfig;
use LLPhant\OpenAIConfig;
use RoachPHP\ItemPipeline\ItemInterface;
use RoachPHP\Roach;
use RoachPHP\Spider\Configuration\Overrides;

class ProcessScrapeJob implements ShouldQueue
{
    use Queueable;

    private int $countScraped;

    /**
     * Create a new job instance.
     */
    public function __construct(private array $urls)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->countScraped = 0;

        Redis::flushAll();

        /*
        OPTIMIZATIONS TO CHECK OUT FOR
        Retrieval Augmentation Generation (RAG)

        - Use other DB instead of Redis? Milvus?
        - Pre-retrieval
            - Eliminate irrelevant pages
            - Eliminate noise data from pages (stop words, html tags, special chars, header/footer/nav)
            - Add metadata to pages (title, url)
            - Optimize chunk size?
            - Chunk overlapping!
            - Small-to-big retrieval (parent doc or sentence window retrieval)
            - Multi-query retrieval
            - HyDE (hypothetical document embeddings)
            - Stepback prompt (not sure if useful)
        - Post-retrieval
            - Re-ranking
            - Prompt compression
        - Review system message
        */

        Roach::startSpider(
            ContentGrabberX::class,
            overrides: new Overrides(
                startUrls: $this->urls,
                itemProcessors: [
                    [
                        CallbackItemProcessor::class,
                        ['callback' => fn (ItemInterface $item) => $this->handleItem($item)],
                    ],
                ],
            )
        );

        $chatController = new ChatController();
        $chatController->updateCentroidVector('p2prag_data');

        event(new ScrapeDone());
    }

    private function handleItem(ItemInterface $item): void
    {
        $this->countScraped++;

        $filename = str($item->get('url'))->slug() . '.txt';

        file_put_contents(resource_path("scrape/{$filename}"), $item->get('text'));

        // $config = new OpenAIConfig();
        // $config->model = 'gpt4o-mini';
        // $config->apiKey = '';

        $config = new OllamaConfig();
        $config->model = 'nomic-embed-text';
        $config->url = 'http://ollama:11434/api/';

        $embeddingGenerator = new OllamaEmbeddingGenerator($config);

        $dataReader = new FileDataReader(resource_path("scrape/{$filename}"));
        $documents = $dataReader->getDocuments();

        $documents = array_map(function (Document $document) use ($item) {
            $document->sourceName = $item->get('url');
            return $document;
        }, $documents);

        $splittedDocuments = DocumentSplitter::splitDocuments($documents, 1000, '.', 2);
        $formattedDocuments = EmbeddingFormatter::formatEmbeddings($splittedDocuments, implode('. ', [
            "URL of this page is {$item->get('url')}",
            "Title of this page is {$item->get('title')}",
        ]) . '.');

        // $embeddingGenerator = new OpenAI3SmallEmbeddingGenerator($config);
        $embeddedDocuments = $embeddingGenerator->embedDocuments($formattedDocuments);

        $vectorStore = new RedisVectorStore(Redis::connection()->client(), 'p2prag_data');
        $vectorStore->addDocuments($embeddedDocuments);

        $directory = resource_path('scrape');
        if (File::exists($directory)) {
            File::cleanDirectory($directory); // Deletes all files but keeps the directory
        }

        event(new UrlScraped(
            $this->countScraped,
            count($this->urls),
            $item->get('url'),
            // $item->get('text'),
        ));
    }
}
