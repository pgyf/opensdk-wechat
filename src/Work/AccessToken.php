<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\Work;

use Pgyf\Opensdk\Kernel\Contracts\RefreshableAccessToken;
use Pgyf\Opensdk\Kernel\Exceptions\HttpException;
use function intval;
use function is_string;
use function json_encode;
use const JSON_UNESCAPED_UNICODE;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use function sprintf;
//use Pgyf\Opensdk\Kernel\Symfony\Component\Cache\Adapter\FilesystemAdapter;
//use Pgyf\Opensdk\Kernel\Symfony\Component\Cache\Psr16Cache;
use Pgyf\Opensdk\Kernel\Symfony\Component\HttpClient\HttpClient;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\HttpClientInterface;

class AccessToken implements RefreshableAccessToken
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
    protected $secret;

    /**
     * @var string
     */
    protected $key = null;


    public function __construct(
        string $corpId,
        string $secret,
        string $key = null,
        CacheInterface $cache = null,
        HttpClientInterface $httpClient = null
    ) {
        $this->corpId = $corpId;
        $this->secret = $secret;
        $this->key = $key;
        $this->httpClient = $httpClient ?? HttpClient::create(['base_uri' => 'https://qyapi.weixin.qq.com/']);
        //$this->cache = $cache ?? new Psr16Cache(new FilesystemAdapter(namespace: 'easywechat', defaultLifetime: 1500));
        if(!empty($cache)){
            $this->cache = $cache;
        }
    }

    public function getKey(): string
    {
        return $this->key ?? $this->key = sprintf('work.access_token.%s.%s', $this->corpId, $this->secret);
    }

    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @return string
     *
     * @throws HttpException
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getToken(): string
    {
        $token = $this->cache->get($this->getKey());

        if ((bool) $token && is_string($token)) {
            return $token;
        }

        return $this->refresh();
    }

    /**
     * @return array<string, string>
     *
     * @throws HttpException
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function toQuery(): array
    {
        return ['access_token' => $this->getToken()];
    }


    public function toHeader(): array
    {
        return [];
    }


    /**
     * @throws HttpException
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function refresh(): string
    {
        $response = $this->httpClient->request('GET', '/cgi-bin/gettoken', [
            'query' => [
                'corpid' => $this->corpId,
                'corpsecret' => $this->secret,
            ],
        ])->toArray(false);

        if (empty($response['access_token'])) {
            throw new HttpException('Failed to get access_token: '.json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        $this->cache->set($this->getKey(), $response['access_token'], intval($response['expires_in']));

        return $response['access_token'];
    }
}
