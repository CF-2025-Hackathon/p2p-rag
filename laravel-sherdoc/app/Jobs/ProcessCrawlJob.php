<?php

namespace App\Jobs;

use App\Events\CrawlDone;
use App\Events\CrawlUpdated;
use App\Spiders\CallbackItemProcessor;
use App\Spiders\LinkFinder;
use App\Spiders\LinkFinderM;
use App\Spiders\LinkFinderX;
use App\Spiders\LinkFinderZ;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RoachPHP\ItemPipeline\ItemInterface;
use RoachPHP\Roach;
use RoachPHP\Spider\Configuration\Overrides;

class ProcessCrawlJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private string $url)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Roach::startSpider(
            LinkFinderZ::class,
            overrides: new Overrides(
                itemProcessors: [
                    [
                        CallbackItemProcessor::class,
                        ['callback' => fn (ItemInterface $item) => $this->handleItem($item)],
                    ],
                ],
            ),
            context: [
                'url' => $this->url,
            ],
        );

        event(new CrawlDone());
    }

    private function handleItem(ItemInterface $item): void
    {
        event(new CrawlUpdated($item->get('link')));
    }
}
