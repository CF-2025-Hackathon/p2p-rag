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

class LinkFinderZ extends BasicSpider
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
        dump("Now on: " . $response->getRequest()->getUri());
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

        // dump($data);

        foreach ($data as $item) {
            // TODO: handle relative links
            if ($this->isValidLink($item)) {
                // dump($item['link']);
                // yield $this->item($item);

                if (str_starts_with($item['link'], '/')) {
                    // $item['link'] = rtrim($this->context['url'], '/') . '/' . ltrim($item['link'], '/');

                    // Parse host name from URI
                    $scheme = parse_url($response->getRequest()->getUri(), PHP_URL_SCHEME);
                    $host = parse_url($response->getRequest()->getUri(), PHP_URL_HOST);
                    $item['link'] = $scheme . '://' . rtrim($host, '/') . '/' . ltrim($item['link'], '/');
                }

                // $cookieJar = CookieJar::fromArray([
                //     'frontend_lang' => 'nl_BE'
                // ], 'shop.dzjing.be');

                yield $this->request('GET', $item['link'], 'parse');
            }
        }
    }

    /** @return Request[] */
    protected function initialRequests(): array
    {
        // $cookieJar = CookieJar::fromArray([
        // ], 'masters-elektriciteit.be');

        return [
            new Request(
                'GET',
                'https://www.mediclowns.org/',
                [$this, 'parse'],
            ),
            // new Request(
            //     'GET',
            //     'https://gopa.be/',
            //     [$this, 'parse'],
            // ),
            // new Request(
            //     'GET',
            //     'https://www.brutbrut.be/',
            //     [$this, 'parse'],
            // ),
            // new Request(
            //     'GET',
            //     'https://emtbvba.be/',
            //     [$this, 'parse'],
            // ),
            // new Request(
            //     'GET',
            //     'https://www.beeradvocate.com/beer/',
            //     [$this, 'parse'],
            // ),
            // new Request(
            //     'GET',
            //     'https://syn.io/',
            //     [$this, 'parse'],
            // ),
            // new Request(
            //     'GET',
            //     'https://www.hsm-interieur.be/',
            //     [$this, 'parse'],
            // ),
            // new Request(
            //     'GET',
            //     'https://majoli-interieurwerken.be/',
            //     [$this, 'parse'],
            // ),
            // new Request(
            //     'GET',
            //     'https://www.purephotography.be/',
            //     [$this, 'parse'],
            // ),
            // new Request(
            //     'GET',
            //     'https://www.gregshomefood.be/',
            //     [$this, 'parse'],
            // ),

            // new Request(
            //     'GET',
            //     'https://www.craftbeer.com',
            //     [$this, 'parse'],
            // ),

            // new Request(
            //     'GET',
            //     'https://hackathon.cloudfest.com/',
            //     [$this, 'parse'],
            // ),

            // new Request(
            //     'GET',
            //     'https://cloudfest.com/',
            //     [$this, 'parse'],
            // ),
        ];
    }

    private function isValidLink(array $item): bool
    {
        // Specific for ME optimization right now
        if (str_contains($item['link'], '/fr') || str_contains($item['link'], '/en') || str_contains($item['link'], '/de')) {
            return false;
        }

        // Specific for ME optimization right now
        if (str_contains($item['link'], '?lang=')) {
            return false;
        }

        // Specific for ME optimization right now
        if (str_contains($item['link'], '?product_cat=')) {
            return false;
        }

        // Specific for ME optimization right now
        if (str_contains($item['link'], '?products-per-page=')) {
            return false;
        }

        // Specific for ME optimization right now
        if (str_contains($item['link'], '?filter')) {
            return false;
        }

        // Specific for ME optimization right now
        if (str_contains($item['link'], '/evenementen/') || str_contains($item['link'], '#tribe-tikckets') || str_contains($item['link'], '/?ical=') || str_contains($item['link'], '/?outlook=') || str_contains($item['link'], '/?ical=') || str_contains($item['link'], '/event/')) {
            return false;
        }

        if (str_contains($item['link'], 'cookie') || str_contains($item['link'], 'privacy') || str_contains($item['link'], 'algeme') || str_starts_with($item['link'], '//')) {
            return false;
        }

        if (str_contains($item['link'], 'onewebmedia')) {
            return false;
        }

        if (str_contains($item['link'], 'wp-login') || str_contains($item['link'], '/author/') || str_contains($item['link'], '#respond') || str_contains($item['link'], '/tag/') || str_contains($item['link'], '/category/') | str_contains($item['link'], '#nieuwsbrief')) {
            return false;
        }

        if (str_contains($item['link'], '?utm_source=')) {
            return false;
        }

        if (str_contains($item['link'], '/cdn-cgi/')) {
            return false;
        }

        if (!str_starts_with($item['link'], '/') && !str_starts_with($item['link'], 'https://www.mediclowns.org/') && !str_starts_with($item['link'], 'https://gopa.be/') && !str_starts_with($item['link'], 'https://www.beeradvocate.com/beer/') && !str_starts_with($item['link'], 'https://emtbvba.be/') && !str_starts_with($item['link'], 'https://bloemenhofke.be/') && !str_starts_with($item['link'], 'https://syn.io/') && !str_starts_with($item['link'], 'https://www.jovado.be/') && !str_starts_with($item['link'], 'https://www.craftbeer.com/styles') && !str_starts_with($item['link'], 'https://www.craftbeer.com/beer-styles') && !str_starts_with($item['link'], 'https://hackathon.cloudfest.com/')) {
            return false;
        }

        if (str_ends_with($item['link'], '.pdf') || str_ends_with($item['link'], '.jpg') || str_ends_with($item['link'], '.png') || str_ends_with($item['link'], '.jpeg') || str_ends_with($item['link'], '.gif') || str_ends_with($item['link'], '.svg') || str_ends_with($item['link'], '.webp')) {
            return false;
        }

        return true;
    }
}
