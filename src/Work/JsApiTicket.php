<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\Work;

use Pgyf\Opensdk\Kernel\Exceptions\HttpException;
use function intval;
use function is_string;
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

class JsApiTicket
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
    protected $key;

    public function __construct(
        string $corpId,
        string $key = null,
        CacheInterface $cache = null,
        HttpClientInterface $httpClient = null
    ) {
        $this->corpId = $corpId;
        $this->key = $key;
        $this->httpClient = $httpClient ?? HttpClient::create(['base_uri' => 'https://qyapi.weixin.qq.com/']);
        //$this->cache = $cache ?? new Psr16Cache(new FilesystemAdapter(namespace: 'easywechat', defaultLifetime: 1500));
        if(!empty($cache)){
            $this->cache = $cache;
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @throws HttpException
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function createConfigSignature(string $url, string $nonce, int $timestamp): array
    {
        return [
            'appId' => $this->corpId,
            'nonceStr' => $nonce,
            'timestamp' => $timestamp,
            'url' => $url,
            'signature' => $this->getTicketSignature($this->getTicket(), $nonce, $timestamp, $url),
        ];
    }

    public function getTicketSignature(string $ticket, string $nonce, int $timestamp, string $url): string
    {
        return sha1(sprintf('jsapi_ticket=%s&noncestr=%s&timestamp=%s&url=%s', $ticket, $nonce, $timestamp, $url));
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @throws TransportExceptionInterface
     * @throws HttpException
     * @throws ServerExceptionInterface
     */
    public function getTicket(): string
    {
        $key = $this->getKey();
        $ticket = $this->cache->get($key);

        if ((bool) $ticket && is_string($ticket)) {
            return $ticket;
        }

        $response = $this->httpClient->request('GET', '/cgi-bin/get_jsapi_ticket')->toArray(false);

        if (empty($response['ticket'])) {
            throw new HttpException('Failed to get jssdk ticket: '.json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        $this->cache->set($key, $response['ticket'], intval($response['expires_in']));

        return $response['ticket'];
    }

    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key ?? $this->key = sprintf('work.jsapi_ticket.%s', $this->corpId);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @throws TransportExceptionInterface
     * @throws HttpException
     * @throws ServerExceptionInterface
     */
    public function createAgentConfigSignature(int $agentId, string $url, string $nonce, int $timestamp): array
    {
        return [
            'corpid' => $this->corpId,
            'agentid' => $agentId,
            'nonceStr' => $nonce,
            'timestamp' => $timestamp,
            'url' => $url,
            'signature' => $this->getTicketSignature($this->getAgentTicket($agentId), $nonce, $timestamp, $url),
        ];
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws HttpException
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getAgentTicket(int $agentId): string
    {
        $key = $this->getAgentKey($agentId);
        $ticket = $this->cache->get($key);

        if ((bool) $ticket && is_string($ticket)) {
            return $ticket;
        }

        $response = $this->httpClient->request('GET', '/cgi-bin/ticket/get', ['query' => ['type' => 'agent_config']])
            ->toArray(false);

        if (empty($response['ticket'])) {
            throw new HttpException('Failed to get jssdk agentTicket: '.json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        $this->cache->set($key, $response['ticket'], intval($response['expires_in']));

        return $response['ticket'];
    }

    public function getAgentKey(int $agentId): string
    {
        return sprintf('%s.%s', $this->getKey(), $agentId);
    }
}
