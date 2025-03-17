<?php

namespace App\Spiders;

use DOMElement;
use Generator;
use RoachPHP\Downloader\Middleware\RequestDeduplicationMiddleware;
use RoachPHP\Extensions\LoggerExtension;
use RoachPHP\Extensions\StatsCollectorExtension;
use RoachPHP\Http\Request;
use RoachPHP\Http\Response;
use RoachPHP\Spider\BasicSpider;
use RoachPHP\Spider\ParseResult;
use Symfony\Component\DomCrawler\Crawler;

class ContentGrabberX extends BasicSpider
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
        // yield $this->item([
        //     'link' => $response->getRequest()->getUri(),
        // ]);

        $titleNodes = $response->filter('title');
        if ($titleNodes->count() > 0) {
            $title = $titleNodes->text();
        } else {
            $title = $response->getRequest()->getUri();
        }

        // Remove all script and style elements
        $response->filter('script, style, link')->each(function (Crawler $node) {
            foreach ($node as $domElement) {
                $domElement->parentNode->removeChild($domElement);
            }
        });

        // TODO: This only optimized for Dzjing
        $response->filter('#cmplz-cookiebanner-container, .elementor-location-header, .elementor-location-footer, #cmplz-manage-consent, header, footer')->each(function (Crawler $node) {
            foreach ($node as $domElement) {
                $domElement->parentNode->removeChild($domElement);
            }
        });

        // // Convert <br> elements to newlines
        // $response->filter('br')->each(function (Crawler $node) {
        //     foreach ($node as $domElement) {
        //         $newNode = $domElement->ownerDocument->createTextNode("\n");
        //         $domElement->parentNode->replaceChild($newNode, $domElement);
        //     }
        // });

        // // Convert <p> elements to newlines (adding double newlines for paragraph separation)
        // $response->filter('p')->each(function (Crawler $node) {
        //     foreach ($node as $domElement) {
        //         $textContent = $domElement->textContent;
        //         $newNode = $domElement->ownerDocument->createTextNode($textContent . "\n\n");
        //         $domElement->parentNode->replaceChild($newNode, $domElement);
        //     }
        // });

        // Extract plain text from the remaining HTML
        // $plainText = $response->filter('body')->text();
        $plainText = $this->getTextWithNewlines($response->filter('body')); // TODO: this only optimizes content for Dzjing

        // Normalize spaces and newlines
        $plainText = preg_replace('/[ \t]+/', ' ', $plainText); // Replace multiple spaces and tabs with a single space
        $plainText = preg_replace('/\n\s+/', "\n", $plainText); // Replace newlines followed by spaces with a single newline
        $plainText = preg_replace('/[\r\n]+/', "\n\n", $plainText); // Replace multiple newlines with two newlines

        // dump($plainText);

        yield $this->item([
            'url'  => $response->getRequest()->getUri(),
            'text' => trim($plainText),
            'title' => $title,
        ]);
    }

    /**
     * @return Generator<ParseResult>
     */
    public function parseHtml(Response $response): Generator
    {
        // dump("Now on: " . $response->getRequest()->getUri());
        // yield $this->item([
        //     'link' => $response->getRequest()->getUri(),
        // ]);

        $title = $response->filter('title')->text();

        // Remove all script and style elements
        $response->filter('script, style, head, svg, img')->each(function (Crawler $node) {
            foreach ($node as $domElement) {
                $domElement->parentNode->removeChild($domElement);
            }
        });

        // Get all elements in the document
        $elements = $response->filter('*');

        // Iterate over each element and remove its attributes
        $elements->each(function (Crawler $node) {
            $nodeElement = $node->getNode(0);

            if ($nodeElement !== null && $nodeElement instanceOf DOMElement && $nodeElement->attributes !== null) {
                // Remove all attributes from the element
                foreach ($nodeElement->attributes as $attribute) {
                    // dump($attribute->nodeName);
                    $nodeElement->removeAttribute($attribute->nodeName);
                }
            }
        });

        // dump($response->text());

        yield $this->item([
            'title' => $title,
            'text' => trim($response->html()),
        ]);
    }

    // Manually traverse the DOM and concatenate text while preserving newlines
    private function getTextWithNewlines($crawler)
    {
        $text = '';

        foreach ($crawler as $domElement) {
            foreach ($domElement->childNodes as $childNode) {
                if ($childNode->nodeType === XML_TEXT_NODE) {
                    $text .= $childNode->nodeValue;
                } elseif ($childNode->nodeType === XML_ELEMENT_NODE) {
                    if ($childNode->nodeName === 'br') {
                        $text .= "\n";
                    } elseif ($childNode->nodeName === 'p') {
                        $text .= $this->getTextWithNewlines(new Crawler($childNode)) . "\n\n";
                    } else {
                        $text .= $this->getTextWithNewlines(new Crawler($childNode));
                    }
                }
            }
        }

        return $text;
    }
}
