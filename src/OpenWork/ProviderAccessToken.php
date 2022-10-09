<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenWork;

use Pgyf\Opensdk\Kernel\Contracts\RefreshableAccessToken as RefreshableAccessTokenInterface;
use Pgyf\Opensdk\Kernel\Exceptions\HttpException;
use function intval;
use const JSON_UNESCAPED_UNICODE;
use Psr\SimpleCache\CacheInterface;
//use Pgyf\Opensdk\Kernel\Symfony\Component\Cache\Adapter\FilesystemAdapter;
//use Pgyf\Opensdk\Kernel\Symfony\Component\Cache\Psr16Cache;
use Pgyf\Opensdk\Kernel\Symfony\Component\HttpClient\HttpClient;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\HttpClientInterface;

class ProviderAccessToken implements RefreshableAccessTokenInterface
{
    /**
     * @var HttpClientInterface
     */
    protected $httpClient;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var string
     */
    protected $corpId;

    /**
     * @var string
     */
    protected $providerSecret;

    /**
     * @var string
     */
    protected $key;


    public function __construct(
        string $corpId,
        string $providerSecret,
        string $key = null,
        CacheInterface $cache = null,
        HttpClientInterface $httpClient = null
    ) {
        $this->corpId = $corpId;
        $this->providerSecret = $providerSecret;
        $this->key = $key;
        $this->httpClient = $httpClient ?? HttpClient::create(['base_uri' => 'https://qyapi.weixin.qq.com/']);
        //$this->cache = $cache ?? new Psr16Cache(new FilesystemAdapter(namespace: 'easywechat', defaultLifetime: 1500));
        if(!empty($cache)){
            $this->cache = $cache;
        }
    }

    public function getKey(): string
    {
        return $this->key ?? $this->key = \sprintf('wechat.open_work.access_token.%s.%s', $this->corpId, $this->providerSecret);
    }

    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\HttpException
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getToken(): string
    {
        $token = $this->cache->get($this->getKey());

        if ((bool) $token && \is_string($token)) {
            return $token;
        }

        return $this->refresh();
    }

    /**
     * @return array<string, string>
     *
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\HttpException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function toQuery(): array
    {
        return ['provider_access_token' => $this->getToken()];
    }


    public function toHeader(): array
    {
        return [];
    }

    /**
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\HttpException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function refresh(): string
    {
        $response = $this->httpClient->request('POST', 'cgi-bin/service/get_provider_token', [
            'json' => [
                'corpid' => $this->corpId,
                'provider_secret' => $this->providerSecret,
            ],
        ])->toArray(false);

        if (empty($response['provider_access_token'])) {
            throw new HttpException('Failed to get provider_access_token: '.\json_encode(
                $response,
                JSON_UNESCAPED_UNICODE
            ));
        }

        $this->cache->set($this->getKey(), $response['provider_access_token'], intval($response['expires_in']) - 100);

        return $response['provider_access_token'];
    }
}
