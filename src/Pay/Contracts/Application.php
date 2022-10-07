<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\Pay\Contracts;

use Pgyf\Opensdk\Kernel\Contracts\Config;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\HttpClientInterface;

interface Application
{
    public function getMerchant(): Merchant;

    public function getConfig(): Config;

    public function getHttpClient(): HttpClientInterface;

    public function getClient(): HttpClientInterface;
}
