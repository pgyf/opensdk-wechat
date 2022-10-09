<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenPlatform\Contracts;

use Pgyf\Opensdk\Kernel\Contracts\AccessToken as AccessTokenInterface;
use Pgyf\Opensdk\Kernel\Contracts\Config as ConfigInterface;
use Pgyf\Opensdk\Kernel\Contracts\Server as ServerInterface;
use Pgyf\Opensdk\Kernel\Encryptor;
use Pgyf\Opensdk\Kernel\HttpClient\AccessTokenAwareClient;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\HttpClientInterface;
use Pgyf\Opensdk\Wechat\MiniApp\Application as MiniAppApplication;
use Pgyf\Opensdk\Wechat\OfficialAccount\Application as OfficialAccountApplication;
use Pgyf\Opensdk\Kernel\Socialite\Contracts\ProviderInterface;
use Pgyf\Opensdk\Wechat\OpenPlatform\AuthorizerAccessToken;

interface Application
{
    public function getAccount(): Account;

    public function getEncryptor(): Encryptor;

    public function getServer(): ServerInterface;

    public function getRequest(): ServerRequestInterface;

    public function getClient(): AccessTokenAwareClient;

    public function getHttpClient(): HttpClientInterface;

    public function getConfig(): ConfigInterface;

    public function getComponentAccessToken(): AccessTokenInterface;

    public function getCache(): CacheInterface;

    public function getOAuth(): ProviderInterface;

    /**
     * @param  array<string, mixed>  $config
     */
    public function getMiniApp(AuthorizerAccessToken $authorizerAccessToken, array $config): MiniAppApplication;

    /**
     * @param  array<string, mixed>  $config
     */
    public function getOfficialAccount(
        AuthorizerAccessToken $authorizerAccessToken,
        array $config
    ): OfficialAccountApplication;
}
