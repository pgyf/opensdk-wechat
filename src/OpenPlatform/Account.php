<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenPlatform;

use Pgyf\Opensdk\Wechat\OpenPlatform\Contracts\Account as AccountInterface;

class Account implements AccountInterface
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
    protected $token;
    /**
     * @var string
     */
    protected $aesKey;


    public function __construct(
         string $appId,
         string $secret,
         string $token,
         string $aesKey
    ) {
        $this->appId    = $appId;
        $this->secret   = $secret;
        $this->token    = $token;
        $this->aesKey   = $aesKey;
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getAesKey(): string
    {
        return $this->aesKey;
    }
}
