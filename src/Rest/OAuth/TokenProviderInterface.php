<?php

declare(strict_types=1);

namespace PayumPayU\Rest\OAuth;

interface TokenProviderInterface
{
    public function token(Grant $grant): AccessToken;
}
