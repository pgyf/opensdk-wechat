<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\Pay;

use Pgyf\Opensdk\Kernel\Contracts\Config as ConfigInterface;
use Pgyf\Opensdk\Kernel\Contracts\Server as ServerInterface;
use Pgyf\Opensdk\Kernel\Support\PrivateKey;
use Pgyf\Opensdk\Kernel\Support\PublicKey;
use Pgyf\Opensdk\Kernel\Traits\InteractWithConfig;
use Pgyf\Opensdk\Kernel\Traits\InteractWithHttpClient;
use Pgyf\Opensdk\Kernel\Traits\InteractWithServerRequest;
use Pgyf\Opensdk\Kernel\Symfony\Contracts\HttpClient\HttpClientInterface;
use Pgyf\Opensdk\Wechat\Pay\Contracts\Merchant as MerchantInterface;

class Application implements \Pgyf\Opensdk\Wechat\Pay\Contracts\Application
{
    use InteractWithConfig;
    use InteractWithHttpClient;
    use InteractWithServerRequest;

    /**
     * @var ServerInterface|null
     */
    protected $server = null;

    /**
     * @var HttpClientInterface|null
     */
    protected $client = null;

    /**
     * @var MerchantInterface|null
     */
    protected $merchant = null;

    /**
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidArgumentException
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidConfigException
     */
    public function getUtils(): Utils
    {
        return new Utils($this->getMerchant());
    }

    /**
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidArgumentException
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidConfigException
     */
    public function getMerchant(): MerchantInterface
    {
        if (! $this->merchant) {
            $this->merchant = new Merchant(
                $this->config['mch_id'], /** @phpstan-ignore-line */
                new PrivateKey((string) $this->config['private_key']), /** @phpstan-ignore-line */
                new PublicKey((string) $this->config['certificate']), /** @phpstan-ignore-line */
                (string) $this->config['secret_key'], /** @phpstan-ignore-line */
                (string) $this->config['v2_secret_key'], /** @phpstan-ignore-line */
                $this->config->has('platform_certs') ? (array) $this->config['platform_certs'] : []/** @phpstan-ignore-line */
            );
        }

        return $this->merchant;
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
                $this->getMerchant(),
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

    public function setConfig(ConfigInterface $config): self
    {
        $this->config = $config;

        return $this;
    }

    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    /**
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidArgumentException
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidConfigException
     */
    public function getClient(): HttpClientInterface
    {
        return $this->client ?? $this->client = (new Client(
            $this->getMerchant(),
            $this->getHttpClient(),
            (array) $this->config->get('http', [])
        ))->setPresets($this->config->all());
    }

    public function setClient(HttpClientInterface $client): self
    {
        $this->client = $client;

        return $this;
    }
}
