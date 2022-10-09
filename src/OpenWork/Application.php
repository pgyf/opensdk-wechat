<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenWork;

use function array_merge;
use Pgyf\Opensdk\Kernel\Contracts\AccessToken as AccessTokenInterface;
use Pgyf\Opensdk\Kernel\Contracts\Server as ServerInterface;
use Pgyf\Opensdk\Kernel\Exceptions\HttpException;
use Pgyf\Opensdk\Kernel\HttpClient\AccessTokenAwareClient;
use Pgyf\Opensdk\Kernel\HttpClient\Response;
use Pgyf\Opensdk\Kernel\Traits\InteractWithCache;
use Pgyf\Opensdk\Kernel\Traits\InteractWithClient;
use Pgyf\Opensdk\Kernel\Traits\InteractWithConfig;
use Pgyf\Opensdk\Kernel\Traits\InteractWithHttpClient;
use Pgyf\Opensdk\Kernel\Traits\InteractWithServerRequest;
use Pgyf\Opensdk\Wechat\OpenWork\Contracts\Account as AccountInterface;
use Pgyf\Opensdk\Wechat\OpenWork\Contracts\Application as ApplicationInterface;
use Pgyf\Opensdk\Wechat\OpenWork\Contracts\SuiteTicket as SuiteTicketInterface;
use Pgyf\Opensdk\Kernel\Socialite\Contracts\ProviderInterface as SocialiteProviderInterface;
use Pgyf\Opensdk\Kernel\Socialite\Providers\OpenWeWork;

class Application implements ApplicationInterface
{
    use InteractWithCache;
    use InteractWithConfig;
    use InteractWithHttpClient;
    use InteractWithServerRequest;
    use InteractWithClient;

    /**
     * @var ServerInterface|null
     */
    protected $server = null;

    /**
     * @var AccountInterface|null
     */
    protected $account = null;

    /**
     * @var Encryptor|null
     */
    protected $encryptor = null;

    /**
     * @var SuiteEncryptor|null
     */
    protected $suiteEncryptor = null;

    /**
     * @var SuiteTicketInterface|null
     */
    protected $suiteTicket = null;

    /**
     * @var AccessTokenInterface|null
     */
    protected $accessToken = null;

    /**
     * @var AccessTokenInterface|null
     */
    protected $suiteAccessToken = null;

    public function getAccount(): AccountInterface
    {
        if (! $this->account) {
            $this->account = new Account(
                (string) $this->config->get('corp_id'), /** @phpstan-ignore-line */
                (string) $this->config->get('provider_secret'), /** @phpstan-ignore-line */
                (string) $this->config->get('suite_id'), /** @phpstan-ignore-line */
                (string) $this->config->get('suite_secret'), /** @phpstan-ignore-line */
                (string) $this->config->get('token'), /** @phpstan-ignore-line */
                (string) $this->config->get('aes_key')/** @phpstan-ignore-line */
            );
        }

        return $this->account;
    }

    public function setAccount(AccountInterface $account): self
    {
        $this->account = $account;

        return $this;
    }

    public function getEncryptor(): \Pgyf\Opensdk\Kernel\Encryptor
    {
        if (! $this->encryptor) {
            $this->encryptor = new Encryptor(
                $this->getAccount()->getCorpId(),
                $this->getAccount()->getToken(),
                $this->getAccount()->getAesKey()
            );
        }

        return $this->encryptor;
    }

    public function setEncryptor(Encryptor $encryptor): self
    {
        $this->encryptor = $encryptor;

        return $this;
    }

    public function getSuiteEncryptor(): \Pgyf\Opensdk\Kernel\Encryptor
    {
        if (! $this->suiteEncryptor) {
            $this->suiteEncryptor = new SuiteEncryptor(
                $this->getAccount()->getSuiteId(),
                $this->getAccount()->getToken(),
                $this->getAccount()->getAesKey()
            );
        }

        return $this->suiteEncryptor;
    }

    public function setSuiteEncryptor(SuiteEncryptor $encryptor): self
    {
        $this->suiteEncryptor = $encryptor;

        return $this;
    }

    /**
     * @return  Server|ServerInterface
     * 
     * @throws \ReflectionException
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidArgumentException
     * @throws \Throwable
     */
    public function getServer(): ServerInterface
    {
        if (! $this->server) {
            $this->server = new Server(
                $this->getSuiteEncryptor(),
                $this->getEncryptor(),
                $this->getRequest()
            );

            $this->server->withDefaultSuiteTicketHandler(function (Message $message, \Closure $next): mixed {
                if ($message->SuiteId === $this->getAccount()->getSuiteId()) {
                    $this->getSuiteTicket()->setTicket($message->SuiteTicket);
                }

                return $next($message);
            });
        }

        return $this->server;
    }

    public function setServer(ServerInterface $server): self
    {
        $this->server = $server;

        return $this;
    }

    public function getProviderAccessToken(): AccessTokenInterface
    {
        if (! $this->accessToken) {
            $this->accessToken = new ProviderAccessToken(
                $this->getAccount()->getCorpId(),
                $this->getAccount()->getProviderSecret(),
                null,
                $this->getCache(),
                $this->getHttpClient()
            );
        }

        return $this->accessToken;
    }

    public function setProviderAccessToken(AccessTokenInterface $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getSuiteAccessToken(): AccessTokenInterface
    {
        if (! $this->suiteAccessToken) {
            $this->suiteAccessToken = new SuiteAccessToken(
                $this->getAccount()->getSuiteId(),
                $this->getAccount()->getSuiteSecret(),
                $this->getSuiteTicket(),
                null,
                $this->getCache(),
                $this->getHttpClient()
            );
        }

        return $this->suiteAccessToken;
    }

    public function setSuiteAccessToken(AccessTokenInterface $accessToken): self
    {
        $this->suiteAccessToken = $accessToken;

        return $this;
    }

    public function getSuiteTicket(): SuiteTicketInterface
    {
        if (! $this->suiteTicket) {
            $this->suiteTicket = new SuiteTicket(
                $this->getAccount()->getSuiteId(),
                $this->getCache()
            );
        }

        return $this->suiteTicket;
    }

    public function setSuiteTicket(SuiteTicketInterface $suiteTicket): SuiteTicketInterface
    {
        $this->suiteTicket = $suiteTicket;

        return $this->suiteTicket;
    }

    /**
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\HttpException
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     */
    public function getAuthorization(
        string $corpId,
        string $permanentCode,
        ?AccessTokenInterface $suiteAccessToken = null
    ): Authorization {
        $suiteAccessToken = $suiteAccessToken ?? $this->getSuiteAccessToken();

        $response = $this->getHttpClient()->request('POST', 'cgi-bin/service/get_auth_info', [
            'query' => [
                'suite_access_token' => $suiteAccessToken->getToken(),
            ],
            'json' => [
                'auth_corpid' => $corpId,
                'permanent_code' => $permanentCode,
            ],
        ])->toArray(false);

        if (empty($response['auth_corp_info'])) {
            throw new HttpException('Failed to get auth_corp_info: '.json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        return new Authorization($response);
    }

    /**
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\HttpException
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     */
    public function getAuthorizerAccessToken(
        string $corpId,
        string $permanentCode,
        AccessTokenInterface $suiteAccessToken = null
    ): AuthorizerAccessToken {
        $suiteAccessToken = $suiteAccessToken ?? $this->getSuiteAccessToken();
        $response = $this->getHttpClient()->request('POST', 'cgi-bin/service/get_corp_token', [
            'query' => [
                'suite_access_token' => $suiteAccessToken->getToken(),
            ],
            'json' => [
                'auth_corpid' => $corpId,
                'permanent_code' => $permanentCode,
            ],
        ])->toArray(false);

        if (empty($response['access_token'])) {
            throw new HttpException('Failed to get access_token: '.json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        return new AuthorizerAccessToken($corpId, $response['access_token']);
    }

    public function createClient(): AccessTokenAwareClient
    {
        return (new AccessTokenAwareClient(
            $this->getHttpClient(),
            $this->getProviderAccessToken(),
            //failureJudge: fn (Response $response) => (bool) ($response->toArray()['errcode'] ?? 0),
            function(Response $response){return (bool) ($response->toArray()['errcode'] ?? 0);},
            (bool) $this->config->get('http.throw', true)
        ))->setPresets($this->config->all());
    }

    public function getOAuth(
        string $suiteId,
        AccessTokenInterface $suiteAccessToken = null
    ): SocialiteProviderInterface {
        $suiteAccessToken = $suiteAccessToken ?? $this->getSuiteAccessToken();

        return (new OpenWeWork([
            'client_id' => $suiteId,
            'redirect_url' => $this->config->get('oauth.redirect_url'),
        ]))->withSuiteTicket($this->getSuiteTicket()->getTicket())
            ->withSuiteAccessToken($suiteAccessToken->getToken())
            ->scopes((array) $this->config->get('oauth.scopes', ['snsapi_base']));
    }

    public function getCorpOAuth(
        string $corpId,
        ?AccessTokenInterface $suiteAccessToken = null
    ): SocialiteProviderInterface {
        $suiteAccessToken = $suiteAccessToken ?? $this->getSuiteAccessToken();

        return (new OpenWeWork([
            'client_id' => $corpId,
            'redirect_url' => $this->config->get('oauth.redirect_url'),
        ]))->withSuiteTicket($this->getSuiteTicket()->getTicket())
            ->withSuiteAccessToken($suiteAccessToken->getToken())
            ->scopes((array) $this->config->get('oauth.scopes', ['snsapi_base']));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getHttpClientDefaultOptions(): array
    {
        return array_merge(
            ['base_uri' => 'https://qyapi.weixin.qq.com/'],
            (array) $this->config->get('http', [])
        );
    }
}
