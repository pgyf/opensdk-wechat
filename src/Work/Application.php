<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\Work;

use function array_merge;
use Pgyf\Opensdk\Kernel\Contracts\AccessToken as AccessTokenInterface;
use Pgyf\Opensdk\Kernel\Contracts\Server as ServerInterface;
use Pgyf\Opensdk\Kernel\HttpClient\AccessTokenAwareClient;
use Pgyf\Opensdk\Kernel\HttpClient\Response;
use Pgyf\Opensdk\Kernel\Traits\InteractWithCache;
use Pgyf\Opensdk\Kernel\Traits\InteractWithClient;
use Pgyf\Opensdk\Kernel\Traits\InteractWithConfig;
use Pgyf\Opensdk\Kernel\Traits\InteractWithHttpClient;
use Pgyf\Opensdk\Kernel\Traits\InteractWithServerRequest;
use Pgyf\Opensdk\Wechat\Work\Contracts\Account as AccountInterface;
use Pgyf\Opensdk\Wechat\Work\Contracts\Application as ApplicationInterface;
//use Overtrue\Socialite\Contracts\ProviderInterface as SocialiteProviderInterface;
//use Overtrue\Socialite\Providers\WeWork;

class Application implements ApplicationInterface
{
    use InteractWithConfig;
    use InteractWithCache;
    use InteractWithServerRequest;
    use InteractWithHttpClient;
    use InteractWithClient;

    /**
     * @var Encryptor|null
     */
    protected $encryptor = null;

    /**
     * @var ServerInterface|null
     */
    protected $server = null;

    /**
     * @var AccountInterface|null
     */
    protected $account = null;

    /**
     * @var JsApiTicket|null
     */
    protected $ticket = null;

    /**
     * @var AccessTokenInterface|null
     */
    protected $accessToken = null;

    public function getAccount(): AccountInterface
    {
        if (! $this->account) {
            $this->account = new Account(
                (string) $this->config->get('corp_id'), /** @phpstan-ignore-line */
                (string) $this->config->get('secret'), /** @phpstan-ignore-line */
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
        if (!$this->encryptor) {
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

    /**
     * @return Server|ServerInterface
     * 
     * @throws \ReflectionException
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidArgumentException
     * @throws \Throwable
     */
    public function getServer(): ServerInterface
    {
        if (! $this->server) {
            $this->server = new Server(
                $this->getEncryptor(),
                $this->getRequest()
            );
        }

        return $this->server;
    }

    public function setServer(ServerInterface $server): self
    {
        $this->server = $server;

        return $this;
    }

    public function getAccessToken(): AccessTokenInterface
    {
        if (! $this->accessToken) {
            $this->accessToken = new AccessToken(
                $this->getAccount()->getCorpId(),
                $this->getAccount()->getSecret(),
                null,
                $this->getCache(),
                $this->getHttpClient()
            );
        }

        return $this->accessToken;
    }

    public function setAccessToken(AccessTokenInterface $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getUtils(): Utils
    {
        return new Utils($this);
    }

    public function createClient(): AccessTokenAwareClient
    {
        return (new AccessTokenAwareClient(
            $this->getHttpClient(),
            $this->getAccessToken(),
            //failureJudge: fn (Response $response) => (bool) ($response->toArray()['errcode'] ?? 0),
            function(Response $response){return (bool) ($response->toArray()['errcode'] ?? 0);},
            (bool) $this->config->get('http.throw', true)
        ))->setPresets($this->config->all());
    }

    // public function getOAuth(): SocialiteProviderInterface
    // {
    //     return (new WeWork(
    //         [
    //             'client_id' => $this->getAccount()->getCorpId(),
    //             'client_secret' => $this->getAccount()->getSecret(),
    //             'redirect_url' => $this->config->get('oauth.redirect_url'),
    //         ]
    //     ))->withApiAccessToken($this->getAccessToken()->getToken())
    //         ->scopes((array) $this->config->get('oauth.scopes', ['snsapi_base']));
    // }

    public function getTicket(): JsApiTicket
    {
        if (! $this->ticket) {
            $this->ticket = new JsApiTicket(
                $this->getAccount()->getCorpId(),
                null,
                $this->getCache(),
                $this->getClient()
            );
        }

        return $this->ticket;
    }

    public function setTicket(JsApiTicket $ticket): self
    {
        $this->ticket = $ticket;

        return $this;
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
