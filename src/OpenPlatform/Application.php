<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenPlatform;

use Closure;
use Pgyf\Opensdk\Kernel\Contracts\AccessToken as AccessTokenInterface;
use Pgyf\Opensdk\Kernel\Contracts\Server as ServerInterface;
use Pgyf\Opensdk\Kernel\Encryptor;
use Pgyf\Opensdk\Kernel\Exceptions\BadResponseException;
use Pgyf\Opensdk\Kernel\Exceptions\HttpException;
use Pgyf\Opensdk\Kernel\HttpClient\AccessTokenAwareClient;
use Pgyf\Opensdk\Kernel\HttpClient\Response;
use Pgyf\Opensdk\Kernel\Support\Arr;
use Pgyf\Opensdk\Kernel\Traits\InteractWithCache;
use Pgyf\Opensdk\Kernel\Traits\InteractWithClient;
use Pgyf\Opensdk\Kernel\Traits\InteractWithConfig;
use Pgyf\Opensdk\Kernel\Traits\InteractWithHttpClient;
use Pgyf\Opensdk\Kernel\Traits\InteractWithServerRequest;
use Pgyf\Opensdk\Wechat\MiniApp\Application as MiniAppApplication;
use Pgyf\Opensdk\Wechat\OfficialAccount\Application as OfficialAccountApplication;
use Pgyf\Opensdk\Wechat\OfficialAccount\Config as OfficialAccountConfig;
use Pgyf\Opensdk\Wechat\OpenPlatform\Contracts\Account as AccountInterface;
use Pgyf\Opensdk\Wechat\OpenPlatform\Contracts\Application as ApplicationInterface;
use Pgyf\Opensdk\Wechat\OpenPlatform\Contracts\VerifyTicket as VerifyTicketInterface;
//use Overtrue\Socialite\Contracts\ProviderInterface as SocialiteProviderInterface;
//use Overtrue\Socialite\Providers\WeChat;
use Psr\SimpleCache\InvalidArgumentException;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

use function array_merge;
use function is_string;
use function md5;
use function sprintf;

class Application implements ApplicationInterface
{
    use InteractWithCache;
    use InteractWithConfig;
    use InteractWithClient;
    use InteractWithHttpClient;
    use InteractWithServerRequest;

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
     * @var AccessTokenInterface|null
     */
    protected $componentAccessToken = null;

    /**
     * @var VerifyTicketInterface|null
     */
    protected $verifyTicket = null;

    public function getAccount(): AccountInterface
    {
        if (!$this->account) {
            $this->account = new Account(
                (string) $this->config->get('app_id'),
                (string) $this->config->get('secret'),
                (string) $this->config->get('token'),
                (string) $this->config->get('aes_key')
            );
        }

        return $this->account;
    }

    /**
     * @param AccountInterface $account
     * @return static
     */
    public function setAccount(AccountInterface $account)
    {
        $this->account = $account;

        return $this;
    }

    public function getVerifyTicket(): VerifyTicketInterface
    {
        if (!$this->verifyTicket) {
            $this->verifyTicket = new VerifyTicket(
                $this->getAccount()->getAppId(),
                null,
                $this->getCache()
            );
        }

        return $this->verifyTicket;
    }

    /**
     * @param VerifyTicketInterface $verifyTicket
     * @return static
     */
    public function setVerifyTicket(VerifyTicketInterface $verifyTicket)
    {
        $this->verifyTicket = $verifyTicket;

        return $this;
    }

    public function getEncryptor(): Encryptor
    {
        if (!$this->encryptor) {
            $this->encryptor = new Encryptor(
               $this->getAccount()->getAppId(),
               $this->getAccount()->getToken(),
               $this->getAccount()->getAesKey(),
               $this->getAccount()->getAppId()
            );
        }

        return $this->encryptor;
    }

    /**
     * @param Encryptor $encryptor
     * @return static
     */
    public function setEncryptor(Encryptor $encryptor)
    {
        $this->encryptor = $encryptor;

        return $this;
    }

    /**
     * @return Server|ServerInterface
     * @throws \ReflectionException
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidArgumentException
     * @throws \Throwable
     */
    public function getServer(): ServerInterface
    {
        if (!$this->server) {
            $this->server = new Server(
                $this->getEncryptor(),
                $this->getRequest()
            );
        }

        if ($this->server instanceof Server) {
            $this->server->withDefaultVerifyTicketHandler(
                function (Message $message, Closure $next) {
                    $ticket = $this->getVerifyTicket();
                    if (\is_callable([$ticket, 'setTicket'])) {
                        $ticket->setTicket($message->ComponentVerifyTicket);
                    }
                    return $next($message);
                }
            );
        }

        return $this->server;
    }

    /**
     * @param ServerInterface $server
     * @return static
     */
    public function setServer(ServerInterface $server)
    {
        $this->server = $server;

        return $this;
    }

    public function getAccessToken(): AccessTokenInterface
    {
        return $this->getComponentAccessToken();
    }

    public function getComponentAccessToken(): AccessTokenInterface
    {
        if (!$this->componentAccessToken) {
            $this->componentAccessToken = new ComponentAccessToken(
                $this->getAccount()->getAppId(),
                $this->getAccount()->getSecret(),
                $this->getVerifyTicket(),
                null,
                $this->getCache(),
                $this->getHttpClient()
            );
        }

        return $this->componentAccessToken;
    }

    /**
     * @param AccessTokenInterface $componentAccessToken
     * @return static
     */
    public function setComponentAccessToken(AccessTokenInterface $componentAccessToken)
    {
        $this->componentAccessToken = $componentAccessToken;

        return $this;
    }

    /**
     * @throws HttpException
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws BadResponseException
     */
    public function getAuthorization(string $authorizationCode): Authorization
    {
        $response = $this->getClient()->request(
            'POST',
            'cgi-bin/component/api_query_auth',
            [
                'json' => [
                    'component_appid' => $this->getAccount()->getAppId(),
                    'authorization_code' => $authorizationCode,
                ],
            ]
        )->toArray(false);

        if (empty($response['authorization_info'])) {
            throw new HttpException('Failed to get authorization_info: '.json_encode(
                $response,
                JSON_UNESCAPED_UNICODE
            ));
        }

        return new Authorization($response);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws HttpException
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws BadResponseException
     */
    public function refreshAuthorizerToken(string $authorizerAppId, string $authorizerRefreshToken): array
    {
        $response = $this->getClient()->request(
            'POST',
            'cgi-bin/component/api_authorizer_token',
            [
                'json' => [
                    'component_appid' => $this->getAccount()->getAppId(),
                    'authorizer_appid' => $authorizerAppId,
                    'authorizer_refresh_token' => $authorizerRefreshToken,
                ],
            ]
        )->toArray(false);

        if (empty($response['authorizer_access_token'])) {
            throw new HttpException('Failed to get authorizer_access_token: '.json_encode(
                $response,
                JSON_UNESCAPED_UNICODE
            ));
        }

        return $response;
    }

    /**
     * @throws HttpException
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws BadResponseException
     */
    public function createPreAuthorizationCode(): array
    {
        $response = $this->getClient()->request(
            'POST',
            'cgi-bin/component/api_create_preauthcode',
            [
                'json' => [
                    'component_appid' => $this->getAccount()->getAppId(),
                ],
            ]
        )->toArray(false);

        if (empty($response['pre_auth_code'])) {
            throw new HttpException('Failed to get authorizer_access_token: '.json_encode(
                $response,
                JSON_UNESCAPED_UNICODE
            ));
        }

        return $response;
    }

    /**
     * @param string $callbackUrl
     * @param array $optional
     * @return string
     */
    public function createPreAuthorizationUrl(string $callbackUrl, $optional = []): string
    {
        // 兼容旧版 API 设计
        if (is_string($optional)) {
            $optional = [
                'pre_auth_code' => $optional,
            ];
        } else {
            $optional['pre_auth_code'] = Arr::get($this->createPreAuthorizationCode(), 'pre_auth_code');
        }

        $queries = array_merge(
            $optional,
            [
                'component_appid' => $this->getAccount()->getAppId(),
                'redirect_uri' => $callbackUrl,
            ]
        );

        return 'https://mp.weixin.qq.com/cgi-bin/componentloginpage?'.http_build_query($queries);
    }

    // /**
    //  * @throws \Overtrue\Socialite\Exceptions\InvalidArgumentException
    //  */
    // public function getOAuth(): SocialiteProviderInterface
    // {
    //     return (new WeChat(
    //         [
    //             'client_id' => $this->getAccount()->getAppId(),
    //             'client_secret' => $this->getAccount()->getSecret(),
    //             'redirect_url' => $this->config->get('oauth.redirect_url'),
    //         ]
    //     ))->scopes((array) $this->config->get('oauth.scopes', ['snsapi_userinfo']));
    // }

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws HttpException
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidArgumentException
     * @throws BadResponseException
     */
    public function getOfficialAccountWithRefreshToken(
        string $appId,
        string $refreshToken,
        array $config = []
    ): OfficialAccountApplication {
        return $this->getOfficialAccountWithAccessToken(
            $appId,
            $this->getAuthorizerAccessToken($appId, $refreshToken),
            $config
        );
    }

    /**
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidArgumentException
     */
    public function getOfficialAccountWithAccessToken(
        string $appId,
        string $accessToken,
        array $config = []
    ): OfficialAccountApplication {
        return $this->getOfficialAccount(new AuthorizerAccessToken($appId, $accessToken), $config);
    }

    // /**
    //  * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidArgumentException
    //  */
    public function getOfficialAccount(
        AuthorizerAccessToken $authorizerAccessToken,
        array $config = []
    ): OfficialAccountApplication {
        $config = new OfficialAccountConfig(
            array_merge(
                [
                    'app_id' => $authorizerAccessToken->getAppId(),
                    'token' => $this->config->get('token'),
                    'aes_key' => $this->config->get('aes_key'),
                    'logging' => $this->config->get('logging'),
                    'http' => $this->config->get('http'),
                ],
                $config
            )
        );

        $app = new OfficialAccountApplication($config);

        $app->setAccessToken($authorizerAccessToken);
        $app->setEncryptor($this->getEncryptor());
        //$app->setOAuthFactory($this->createAuthorizerOAuthFactory($authorizerAccessToken->getAppId(), $config));

        return $app;
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws HttpException
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidArgumentException
     * @throws BadResponseException
     */
    public function getMiniAppWithRefreshToken(
        string $appId,
        string $refreshToken,
        array $config = []
    ): MiniAppApplication {
        return $this->getMiniAppWithAccessToken(
            $appId,
            $this->getAuthorizerAccessToken($appId, $refreshToken),
            $config
        );
    }

    /**
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidArgumentException
     */
    public function getMiniAppWithAccessToken(
        string $appId,
        string $accessToken,
        array $config = []
    ): MiniAppApplication {
        return $this->getMiniApp(new AuthorizerAccessToken($appId, $accessToken), $config);
    }

    /**
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidArgumentException
     */
    public function getMiniApp(AuthorizerAccessToken $authorizerAccessToken, array $config = []): MiniAppApplication
    {
        $app = new MiniAppApplication(
            array_merge(
                [
                    'app_id' => $authorizerAccessToken->getAppId(),
                    'token' => $this->config->get('token'),
                    'aes_key' => $this->config->get('aes_key'),
                    'logging' => $this->config->get('logging'),
                    'http' => $this->config->get('http'),
                ],
                $config
            )
        );

        $app->setAccessToken($authorizerAccessToken);
        $app->setEncryptor($this->getEncryptor());

        return $app;
    }

    // protected function createAuthorizerOAuthFactory(string $authorizerAppId, OfficialAccountConfig $config): Closure
    // {
    //     return fn () => (new WeChat(
    //         [
    //             'client_id' => $authorizerAppId,

    //             'component' => [
    //                 'component_app_id' => $this->getAccount()->getAppId(),
    //                 'component_access_token' => fn () => $this->getComponentAccessToken()->getToken(),
    //             ],

    //             'redirect_url' => $this->config->get('oauth.redirect_url'),
    //         ]
    //     ))->scopes((array) $config->get('oauth.scopes', ['snsapi_userinfo']));
    // }

    public function createClient(): AccessTokenAwareClient
    {
        return (new AccessTokenAwareClient(
            $this->getHttpClient(),
            $this->getComponentAccessToken(),
            function(Response $response){
                return !!($response->toArray()['errcode'] ?? 0);
            },
            !!$this->config->get('http.throw', true)
        ))->setPresets($this->config->all());
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @throws TransportExceptionInterface
     * @throws HttpException
     * @throws ServerExceptionInterface
     * @throws BadResponseException
     */
    public function getAuthorizerAccessToken(string $appId, string $refreshToken): string
    {
        $cacheKey = sprintf('wechat.open-platform.authorizer_access_token.%s.%s', $appId, md5($refreshToken));

        /** @phpstan-ignore-next-line */
        $authorizerAccessToken = (string) $this->getCache()->get($cacheKey);

        if (!$authorizerAccessToken) {
            $response = $this->refreshAuthorizerToken($appId, $refreshToken);
            $authorizerAccessToken = (string) $response['authorizer_access_token'];
            $this->getCache()->set($cacheKey, $authorizerAccessToken, intval($response['expires_in'] ?? 7200) - 500);
        }

        return $authorizerAccessToken;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getHttpClientDefaultOptions(): array
    {
        return array_merge(
            ['base_uri' => 'https://api.weixin.qq.com/',],
            (array) $this->config->get('http', [])
        );
    }
}
