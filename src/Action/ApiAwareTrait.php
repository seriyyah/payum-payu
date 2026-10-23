<?php

declare(strict_types=1);

namespace PayumPayU\Action;

use Payum\Core\Exception\UnsupportedApiException;
use PayumPayU\Api;
use PayumPayU\Exception\LogicException;

/**
 * Typed replacement for Payum's ApiAwareTrait.
 */
trait ApiAwareTrait
{
    private ?Api $api = null;

    public function setApi(mixed $api): void
    {
        if (! $api instanceof Api) {
            throw new UnsupportedApiException(sprintf('Not supported api given. It must be an instance of %s.', Api::class));
        }

        $this->api = $api;
    }

    private function api(): Api
    {
        return $this->api ?? throw new LogicException('The API has not been set on the action; register it through the gateway factory.');
    }
}
