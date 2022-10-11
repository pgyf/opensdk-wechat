<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\MiniApp;

use function array_merge;
use Pgyf\Opensdk\Kernel\Contracts\AccessToken as AccessTokenInterface;
use Pgyf\Opensdk\Kernel\Contracts\Server as ServerInterface;
use Pgyf\Opensdk\Kernel\Encryptor;
use Pgyf\Opensdk\Kernel\Exceptions\InvalidConfigException;
use Pgyf\Opensdk\Kernel\HttpClient\AccessTokenAwareClient;
use Pgyf\Opensdk\Kernel\HttpClient\AccessTokenExpiredRetryStrategy;
use Pgyf\Opensdk\Kernel\HttpClient\RequestUtil;
use Pgyf\Opensdk\Kernel\HttpClient\Response;
use Pgyf\Opensdk\Kernel\Support\Str;
use Pgyf\Opensdk\Kernel\Traits\InteractWithCache;
use Pgyf\Opensdk\Kernel\Traits\InteractWithClient;
use Pgyf\Opensdk\Kernel\Traits\InteractWithConfig;
use Pgyf\Opensdk\Kernel\Traits\InteractWithHttpClient;
use Pgyf\Opensdk\Kernel\Traits\InteractWithServerRequest;
use Pgyf\Opensdk\Wechat\MiniApp\Contracts\Account as AccountInterface;
use Pgyf\Opensdk\Wechat\MiniApp\Contracts\Application as ApplicationInterface;
use function is_null;
use Psr\Log\LoggerAwareTrait;
use function str_contains;
//use Symfony\Component\HttpClient\Response\AsyncContext;
//use Symfony\Component\HttpClient\RetryableHttpClient;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
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
     * @var AccessTokenInterface|null
     */
    protected $accessToken = null;

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
     * @return Server|ServerInterface
     * @throws \ReflectionException
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidArgumentException
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
            // failureJudge: fn (
            //     Response $response
            // ) => (bool) ($response->toArray()['errcode'] ?? 0) || ! is_null($response->toArray()['error'] ?? null),
            function(Response $response){ return  (bool) ($response->toArray()['errcode'] ?? 0) || ! is_null($response->toArray()['error'] ?? null);},
            (bool) $this->config->get('http.throw', true)
        ))->setPresets($this->config->all())->retrySetStrategy($this->getRetryStrategy(), (int)$this->config->get('http.max_retries', 1));
    }

    public function getRetryStrategy(): AccessTokenExpiredRetryStrategy
    {
        $retry = $this->config->get('http.retry', []);
        if(is_bool($retry)){
            if($retry === false){
                return null;
            }
            $retry = [];
        }
        $retryConfig = RequestUtil::mergeDefaultRetryOptions($retry);

        // return (new AccessTokenExpiredRetryStrategy($retryConfig))
        //     ->decideUsing(function (AsyncContext $context, ?string $responseContent): bool {
        //         return ! empty($responseContent)
        //             && str_contains($responseContent, '42001')
        //             && str_contains($responseContent, 'access_token expired');
        //     });
        $strategy = new AccessTokenExpiredRetryStrategy(
            // @phpstan-ignore-next-line
            (array) $retryConfig['status_codes'],
            // @phpstan-ignore-next-line
            (int) $retryConfig['delay'],
            // @phpstan-ignore-next-line
            (float) $retryConfig['multiplier'],
            // @phpstan-ignore-next-line
            (int) $retryConfig['max_delay'],
            // @phpstan-ignore-next-line
            (float) $retryConfig['jitter']
        );

        return $strategy
        ->decideUsing(function ($context, ?string $responseContent) {
            return ! empty($responseContent)
                && Str::contains($responseContent, '42001')
                && Str::contains($responseContent, 'access_token expired');
        });
    }

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
