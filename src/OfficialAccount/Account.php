<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OfficialAccount;

use Pgyf\Opensdk\Wechat\OfficialAccount\Contracts\Account as AccountInterface;
use RuntimeException;

class Account implements AccountInterface
{

    protected $appId = '';

    protected $secret = null;

    protected $key = null;

    protected $token = null;

    protected $aesKey = null;

    public function __construct(
        string $appId,
        ?string $secret,
        ?string $token = null,
        ?string $aesKey = null
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
        if (null === $this->secret) {
            throw new RuntimeException('No secret configured.');
        }

        return $this->secret;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getAesKey(): ?string
    {
        return $this->aesKey;
    }
}
