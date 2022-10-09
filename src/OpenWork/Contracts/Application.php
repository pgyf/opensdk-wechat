<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenWork\Contracts;

use Pgyf\Opensdk\Kernel\Contracts\AccessToken;
use Pgyf\Opensdk\Kernel\Contracts\Config;
use Pgyf\Opensdk\Kernel\Contracts\Server;
use Pgyf\Opensdk\Kernel\Encryptor;
use Pgyf\Opensdk\Kernel\HttpClient\AccessTokenAwareClient;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\HttpClientInterface;

interface Application
{
    public function getAccount(): Account;

    public function getEncryptor(): Encryptor;

    public function getSuiteEncryptor(): Encryptor;

    public function getServer(): Server;

    public function getRequest(): ServerRequestInterface;

    public function getClient(): AccessTokenAwareClient;

    public function getHttpClient(): HttpClientInterface;

    public function getConfig(): Config;

    public function getProviderAccessToken(): AccessToken;

    public function getCache(): CacheInterface;
}
