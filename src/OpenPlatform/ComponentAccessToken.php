<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenPlatform;

use Pgyf\Opensdk\Kernel\Contracts\RefreshableAccessToken as RefreshableAccessTokenInterface;
use Pgyf\Opensdk\Kernel\Exceptions\HttpException;
use Pgyf\Opensdk\Wechat\OpenPlatform\Contracts\VerifyTicket as VerifyTicketInterface;
use Pgyf\Opensdk\Kernel\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function abs;
use function intval;
use function json_encode;

class ComponentAccessToken implements RefreshableAccessTokenInterface
{

    /**
     * @var string
     */
    protected $appId;

    /**
     * @var string
     */
    protected $secret;

    /**
     * @var string
     */
    protected $key;

    /**
     * @var VerifyTicketInterface
     */
    protected  $verifyTicket;
    /**
     * @var HttpClientInterface
     */
    protected  $httpClient;


    public function __construct(
        string $appId,
        string $secret,
        $verifyTicket,
        string $key = null,
        $cache = null,
        $httpClient = null
    ) {
        $this->appId            = $appId;
        $this->secret           = $secret;
        $this->verifyTicket     = $verifyTicket;
        $this->key              = $key;
        $this->cache = $cache;
        $this->httpClient = $httpClient ?? HttpClient::create(['base_uri' => 'https://api.weixin.qq.com/']);
    }

    public function getKey(): string
    {
        return $this->key ?? $this->key = \sprintf('wechat.open_platform.component_access_token.%s', $this->appId);
    }

    /**
     * @param string $key
     * @return static
     */
    public function setKey(string $key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\HttpException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getToken(): string
    {
        $token = $this->cache->get($this->getKey());

        if (!empty($token) && \is_string($token)) {
            return $token;
        }

        return $this->refresh();
    }


    /**
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return ['component_access_token' => $this->getToken()];
    }

    /**
     * @return array<string, string>
     */
    public function toHeader(): array
    {
        return [];
    }
 
    public function refresh(): string
    {
        $response = $this->httpClient->request(
            'POST',
            'cgi-bin/component/api_component_token',
            [
                'json' => [
                    'component_appid' => $this->appId,
                    'component_appsecret' => $this->secret,
                    'component_verify_ticket' => $this->verifyTicket->getTicket(),
                ],
            ]
        )->toArray(false);

        if (empty($response['component_access_token'])) {
            throw new HttpException('Failed to get component_access_token: '.json_encode(
                $response,
                JSON_UNESCAPED_UNICODE
            ));
        }

        $this->cache->set(
            $this->getKey(),
            $response['component_access_token'],
            abs(intval($response['expires_in']) - 100)
        );

        return $response['component_access_token'];
    }
}
