<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\Work;

use Pgyf\Opensdk\Wechat\Work\Contracts\Account as AccountInterface;

class Account implements AccountInterface
{

    /**
     * @var string
     */
    protected $corpId;

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
        string $corpId,
        string $secret,
        string $token,
        string $aesKey
    ) {
        $this->corpId = $corpId;
        $this->secret = $secret;
        $this->token = $token;
        $this->aesKey = $aesKey;
    }

    public function getCorpId(): string
    {
        return $this->corpId;
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
