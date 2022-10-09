<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenWork;

use function abs;
use Pgyf\Opensdk\Kernel\Contracts\RefreshableAccessToken as RefreshableAccessTokenInterface;
use Pgyf\Opensdk\Kernel\Exceptions\HttpException;
use Pgyf\Opensdk\Wechat\OpenWork\Contracts\SuiteTicket as SuiteTicketInterface;
use function intval;
use function json_encode;
use const JSON_UNESCAPED_UNICODE;
use Psr\SimpleCache\CacheInterface;
//use Pgyf\Opensdk\Kernel\Symfony\Component\Cache\Adapter\FilesystemAdapter;
//use Pgyf\Opensdk\Kernel\Symfony\Component\Cache\Psr16Cache;
use Pgyf\Opensdk\Kernel\Symfony\Component\HttpClient\HttpClient;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\HttpClientInterface;

class SuiteAccessToken implements RefreshableAccessTokenInterface
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
    protected $suiteId;

    /**
     * @var string
     */
    protected $suiteSecret;

    /**
     * @var SuiteTicketInterface|null
     */
    protected $suiteTicket = null;

    /**
     * @var string|null
     */
    protected $key = null;

    public function __construct(
        string $suiteId,
        string $suiteSecret,
        SuiteTicketInterface $suiteTicket = null,
        string $key = null,
        CacheInterface $cache = null,
        HttpClientInterface $httpClient = null
    ) {
        $this->suiteId = $suiteId;
        $this->suiteSecret = $suiteSecret;
        $this->key = $key;
        $this->httpClient = $httpClient ?? HttpClient::create(['base_uri' => 'https://qyapi.weixin.qq.com/']);
        //$this->cache = $cache ?? new Psr16Cache(new FilesystemAdapter(namespace: 'easywechat', defaultLifetime: 1500));
        if(!empty($cache)){
            $this->cache = $cache;
        }
        if(!empty($suiteTicket)){
            $this->suiteTicket = new SuiteTicket($this->suiteId, $this->cache);
        }
    }

    public function getKey(): string
    {
        return $this->key ?? $this->key = \sprintf('wechat.open_work.suite_access_token.%s.%s', $this->suiteId, $this->suiteSecret);
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
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface|\Psr\SimpleCache\InvalidArgumentException
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
        return ['suite_access_token' => $this->getToken()];
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
        $suite_ticket = '';
        if(!empty($this->suiteTicket)){
            $suite_ticket = $this->suiteTicket->getTicket();
        }
        $response = $this->httpClient->request('POST', 'cgi-bin/service/get_suite_token', [
            'json' => [
                'suite_id' => $this->suiteId,
                'suite_secret' => $this->suiteSecret,
                'suite_ticket' => $suite_ticket,
            ],
        ])->toArray(false);

        if (empty($response['suite_access_token'])) {
            throw new HttpException('Failed to get suite_access_token: '.json_encode(
                $response,
                JSON_UNESCAPED_UNICODE
            ));
        }

        $this->cache->set(
            $this->getKey(),
            $response['suite_access_token'],
            abs(intval($response['expires_in']) - 100)
        );

        return $response['suite_access_token'];
    }
}
