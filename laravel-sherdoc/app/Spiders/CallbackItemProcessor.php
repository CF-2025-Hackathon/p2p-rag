<?php

namespace App\Spiders;

use RoachPHP\ItemPipeline\ItemInterface;
use RoachPHP\ItemPipeline\Processors\ItemProcessorInterface;
use RoachPHP\Support\Configurable;

class CallbackItemProcessor implements ItemProcessorInterface
{
    use Configurable;

    public function processItem(ItemInterface $item): ItemInterface
    {
        if (is_callable($this->option('callback'))) {
            $callback = $this->option('callback');
            $callback($item);
        }

        return $item;
    }

    private function defaultOptions(): array
    {
        return [
            'callback' => null,
        ];
    }
}
