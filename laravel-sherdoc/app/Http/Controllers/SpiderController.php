<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCrawlJob;
use App\Jobs\ProcessScrapeJob;
use App\Spiders\ContentGrabber;
use App\Spiders\LinkFinder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;
use LLPhant\Embeddings\DataReader\FileDataReader;
use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;
use LLPhant\Embeddings\EmbeddingFormatter\EmbeddingFormatter;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAI3SmallEmbeddingGenerator;
use LLPhant\Embeddings\VectorStores\Redis\RedisVectorStore;
use LLPhant\OpenAIConfig;
use RoachPHP\ItemPipeline\ItemInterface;
use RoachPHP\Roach;

class SpiderController extends Controller
{
    public function crawl(Request $request): JsonResponse
    {
        ProcessCrawlJob::dispatch($request->input('url'));

        return new JsonResponse([
            'ok' => true,
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        Redis::flushAll();

        return new JsonResponse([
            'ok' => true,
        ]);
    }

    public function scrape(Request $request): JsonResponse
    {
        ProcessScrapeJob::dispatch($request->input('urls'));

        return new JsonResponse([
            'ok' => true,
        ]);
    }
}
