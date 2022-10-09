<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OfficialAccount;

use function array_merge;
use function call_user_func;
use Pgyf\Opensdk\Kernel\Contracts\AccessToken as AccessTokenInterface;
use Pgyf\Opensdk\Kernel\Contracts\RefreshableAccessToken as RefreshableAccessTokenInterface;
use Pgyf\Opensdk\Kernel\Contracts\Server as ServerInterface;
use Pgyf\Opensdk\Kernel\Encryptor;
use Pgyf\Opensdk\Kernel\Exceptions\InvalidArgumentException;
use Pgyf\Opensdk\Kernel\Exceptions\InvalidConfigException;
use Pgyf\Opensdk\Kernel\HttpClient\AccessTokenAwareClient;
//use Pgyf\Opensdk\Kernel\HttpClient\AccessTokenExpiredRetryStrategy;
//use Pgyf\Opensdk\Kernel\HttpClient\RequestUtil;
use Pgyf\Opensdk\Kernel\HttpClient\Response;
use Pgyf\Opensdk\Kernel\Traits\InteractWithCache;
use Pgyf\Opensdk\Kernel\Traits\InteractWithClient;
use Pgyf\Opensdk\Kernel\Traits\InteractWithConfig;
use Pgyf\Opensdk\Kernel\Traits\InteractWithHttpClient;
use Pgyf\Opensdk\Kernel\Traits\InteractWithServerRequest;
use Pgyf\Opensdk\Wechat\OfficialAccount\Contracts\Account as AccountInterface;
use Pgyf\Opensdk\Wechat\OfficialAccount\Contracts\Application as ApplicationInterface;
use  Pgyf\Opensdk\Kernel\Socialite\Contracts\ProviderInterface as SocialiteProviderInterface;
use  Pgyf\Opensdk\Kernel\Socialite\Providers\WeChat;
use Psr\Log\LoggerAwareTrait;
use function sprintf;
use function str_contains;
//use Symfony\Component\HttpClient\Response\AsyncContext;
//use Symfony\Component\HttpClient\RetryableHttpClient;

class Application implements ApplicationInterface
{
    use InteractWithConfig;
    use InteractWithCache;
    use InteractWithServerRequest;
    use InteractWithHttpClient;
    use InteractWithClient;
    use LoggerAwareTrait;

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
     * @var AccessTokenInterface|RefreshableAccessTokenInterface|null
     */
    protected $accessToken = null;

    /**
     * @var JsApiTicket|null
     */
    protected $ticket = null;

    /**
     * @var \Closure|null
     */
    protected $oauthFactory = null;

    public function getAccount(): AccountInterface
    {
        if (! $this->account) {
            $this->account = new Account(
                (string) $this->config->get('app_id'), /** @phpstan-ignore-line */
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

    /**
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidConfigException
     */
    public function getEncryptor(): Encryptor
    {
        if (! $this->encryptor) {
            $token = $this->getAccount()->getToken();
            $aesKey = $this->getAccount()->getAesKey();

            if (empty($token) || empty($aesKey)) {
                throw new InvalidConfigException('token or aes_key cannot be empty.');
            }

            $this->encryptor = new Encryptor(
                $this->getAccount()->getAppId(),
                $token,
                $aesKey,
                $this->getAccount()->getAppId()
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
     * 
     * @return Server|ServerInterface
     * @throws \ReflectionException
     * @throws InvalidArgumentException
     * @throws \Throwable
     */
    public function getServer(): ServerInterface
    {
        if (! $this->server) {
            $this->server = new Server(
                $this->getRequest(),
                $this->getAccount()->getAesKey() ? $this->getEncryptor() : null
            );
        }

        return $this->server;
    }

    public function setServer(ServerInterface $server): self
    {
        $this->server = $server;

        return $this;
    }

    /**
     * @return AccessTokenInterface|RefreshableAccessTokenInterface
     */
    public function getAccessToken(): AccessTokenInterface
    {
        if (! $this->accessToken) {
            $this->accessToken = new AccessToken(
                $this->getAccount()->getAppId(),
                $this->getAccount()->getSecret(),
                null,
                $this->getCache(),
                $this->getHttpClient()
            );
        }

        return $this->accessToken;
    }

    /**
     * @param AccessTokenInterface|RefreshableAccessTokenInterface $accessToken
     * @return self
     */
    public function setAccessToken($accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function setOAuthFactory(callable $factory): self
    {
        //$this->oauthFactory = fn (Application $app): WeChat => $factory($app);
        $this->oauthFactory = function (Application $app) use ($factory){return $factory($app);};

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getOAuth(): SocialiteProviderInterface
    {
        if (! $this->oauthFactory) {
            // $this->oauthFactory = fn (self $app): SocialiteProviderInterface => (new WeChat(
            //     [
            //         'client_id' => $this->getAccount()->getAppId(),
            //         'client_secret' => $this->getAccount()->getSecret(),
            //         'redirect_url' => $this->config->get('oauth.redirect_url'),
            //     ]
            // ))->scopes((array) $this->config->get('oauth.scopes', ['snsapi_userinfo']));
            $this->oauthFactory = function (self $app) {return (new WeChat(
                [
                    'client_id' => $app->getAccount()->getAppId(),
                    'client_secret' => $app->getAccount()->getSecret(),
                    'redirect_url' => $app->config->get('oauth.redirect_url'),
                ]
            ))->scopes((array) $app->config->get('oauth.scopes', ['snsapi_userinfo']));};
        }

        $provider = call_user_func($this->oauthFactory, $this);

        if (! $provider instanceof SocialiteProviderInterface) {
            throw new InvalidArgumentException(sprintf(
                'The factory must return a %s instance.',
                SocialiteProviderInterface::class
            ));
        }

        return $provider;
    }

    public function getTicket(): JsApiTicket
    {
        if (! $this->ticket) {
            $this->ticket = new JsApiTicket(
                $this->getAccount()->getAppId(),
                $this->getAccount()->getSecret(),
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

    public function getUtils(): Utils
    {
        return new Utils($this);
    }

    public function createClient(): AccessTokenAwareClient
    {
        $httpClient = $this->getHttpClient();

        // if ((bool) $this->config->get('http.retry', false)) {
        //     $httpClient = new RetryableHttpClient(
        //         $httpClient,
        //         $this->getRetryStrategy(),
        //         (int) $this->config->get('http.max_retries', 2) // @phpstan-ignore-line
        //     );
        // }

        return (new AccessTokenAwareClient(
            $httpClient,
            $this->getAccessToken(),
            //failureJudge: fn (Response $response) => (bool) ($response->toArray()['errcode'] ?? 0),
            function(Response $response){return  (bool) ($response->toArray()['errcode'] ?? 0); },
            (bool) $this->config->get('http.throw', true)
        ))->setPresets($this->config->all());
    }

    // public function getRetryStrategy(): AccessTokenExpiredRetryStrategy
    // {
    //     $retryConfig = RequestUtil::mergeDefaultRetryOptions((array) $this->config->get('http.retry', []));

    //     return (new AccessTokenExpiredRetryStrategy($retryConfig))
    //         ->decideUsing(function (AsyncContext $context, ?string $responseContent): bool {
    //             return ! empty($responseContent)
    //                 && str_contains($responseContent, '42001')
    //                 && str_contains($responseContent, 'access_token expired');
    //         });
    // }

    /**
     * @return array<string,mixed>
     */
    protected function getHttpClientDefaultOptions(): array
    {
        return array_merge(
            ['base_uri' => 'https://api.weixin.qq.com/'],
            (array) $this->config->get('http', [])
        );
    }
}
