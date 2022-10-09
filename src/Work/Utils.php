<?php

namespace Pgyf\Opensdk\Wechat\Work;

use Pgyf\Opensdk\Kernel\Exceptions\HttpException;
use Pgyf\Opensdk\Kernel\Support\Str;
use Exception;
use Psr\SimpleCache\InvalidArgumentException;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use function time;

class Utils
{
    /**
     * @var Application
     */
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @param  array<string>  $jsApiList
     * @param  array<string>  $openTagList
     * @return array<string, mixed>
     *
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws HttpException
     * @throws ServerExceptionInterface
     * @throws Exception
     */
    public function buildJsSdkConfig(
        string $url,
        array $jsApiList,
        array $openTagList = [],
        bool $debug = false,
        bool $beta = true
    ): array {
        return array_merge(
            compact('jsApiList', 'openTagList', 'debug', 'beta'),
            $this->app->getTicket()->createConfigSignature($url, Str::random(), time())
        );
    }

    /**
     * @param  array<string>  $jsApiList
     * @param  array<string>  $openTagList
     * @return array<string, mixed>
     *
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @throws TransportExceptionInterface
     * @throws HttpException
     * @throws ServerExceptionInterface
     * @throws Exception
     */
    public function buildJsSdkAgentConfig(
        int $agentId,
        string $url,
        array $jsApiList,
        array $openTagList = [],
        bool $debug = false
    ): array {
        return array_merge(
            compact('jsApiList', 'openTagList', 'debug'),
            $this->app->getTicket()->createAgentConfigSignature($agentId, $url, Str::random(), time())
        );
    }
}
