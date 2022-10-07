<?php

namespace Pgyf\Opensdk\Wechat\OfficialAccount;

use Pgyf\Opensdk\Kernel\Exceptions\HttpException;
use Pgyf\Opensdk\Kernel\Support\Str;
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
     * @param  string  $url
     * @param  array<string>  $jsApiList
     * @param  array<string>  $openTagList
     * @param  bool  $debug
     * @return array<string, mixed>
     *
     * @throws HttpException
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function buildJsSdkConfig(
        string $url,
        array $jsApiList = [],
        array $openTagList = [],
        bool $debug = false
    ): array {
        return array_merge(
            compact('jsApiList', 'openTagList', 'debug'),
            $this->app->getTicket()->configSignature($url, Str::random(), time())
        );
    }
}
