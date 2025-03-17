<?php

namespace App\Spiders;

use Generator;
use GuzzleHttp\Cookie\CookieJar;
use RoachPHP\Downloader\Middleware\RequestDeduplicationMiddleware;
use RoachPHP\Extensions\LoggerExtension;
use RoachPHP\Extensions\StatsCollectorExtension;
use RoachPHP\Http\Request;
use RoachPHP\Http\Response;
use RoachPHP\Spider\BasicSpider;
use RoachPHP\Spider\ParseResult;
use Symfony\Component\DomCrawler\Crawler;

class LinkFinder extends BasicSpider
{
    public array $startUrls = [
        //
    ];

    public array $downloaderMiddleware = [
        RequestDeduplicationMiddleware::class,
    ];

    public array $spiderMiddleware = [
        //
    ];

    public array $itemProcessors = [
        //
    ];

    public array $extensions = [
        LoggerExtension::class,
        StatsCollectorExtension::class,
    ];

    public int $concurrency = 10;

    public int $requestDelay = 1;

    /**
     * @return Generator<ParseResult>
     */
    public function parse(Response $response): Generator
    {
        // dump("Now on: " . $response->getRequest()->getUri());
        yield $this->item([
            'link' => $response->getRequest()->getUri(),
        ]);
        // dump('-----------------------');

        $data = $response
            ->filter('a')
            ->each(function (Crawler $item) {
                return [
                    'link' => $item->attr('href'),
                ];
            });

        dump($data);

        foreach ($data as $item) {
            // TODO: handle relative links
            if ($this->isValidLink($item)) {
                // dump($item['link']);
                // yield $this->item($item);

                if (str_starts_with($item['link'], '/')) {
                    $item['link'] = rtrim($this->context['url'], '/') . '/' . ltrim($item['link'], '/');
                }

                $cookieJar = CookieJar::fromArray([
                    'frontend_lang' => 'nl_BE'
                ], 'shop.dzjing.be');

                yield $this->request('GET', $item['link'], 'parse', ['cookies' => $cookieJar]);
            }
        }
    }

    /** @return Request[] */
    protected function initialRequests(): array
    {
        $cookieJar = CookieJar::fromArray([
            'frontend_lang' => 'nl_BE'
        ], 'shop.dzjing.be');

        return [
            new Request(
                'GET',
                $this->context['url'] ?? 'https://dzjing.be',
                [$this, 'parse'],
                ['cookies' => $cookieJar],
            ),
        ];
    }

    private function isValidLink(array $item): bool
    {
        // Specific for Dzjing optimization right now
        if (str_contains($item['link'], '/fr') || str_contains($item['link'], '/en')) {
            return false;
        }
        if (str_contains($item['link'], 'cookie') || str_contains($item['link'], 'privacy') || str_contains($item['link'], 'algeme') || str_starts_with($item['link'], '//')) {
            return false;
        }

        if (!str_starts_with($item['link'], '/') && !str_starts_with($item['link'], $this->context['url'] ?? 'https://dzjing.be') && !str_starts_with($item['link'], 'https://shop.dzjing.be')) {
            return false;
        }

        if (str_ends_with($item['link'], '.pdf') || str_ends_with($item['link'], '.jpg') || str_ends_with($item['link'], '.png') || str_ends_with($item['link'], '.jpeg') || str_ends_with($item['link'], '.gif') || str_ends_with($item['link'], '.svg') || str_ends_with($item['link'], '.webp')) {
            return false;
        }

        return true;
    }
}
