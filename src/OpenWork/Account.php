<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenWork;

use Pgyf\Opensdk\Wechat\OpenWork\Contracts\Account as AccountInterface;

class Account implements AccountInterface
{
    /**
     * @var string
     */
    protected $corpId;

    /**
     * @var string
     */
    protected $providerSecret;

    /**
     * @var string
     */
    protected $suiteId;

    /**
     * @var string
     */
    protected $suiteSecret;

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
        string $providerSecret,
        string $suiteId,
        string $suiteSecret,
        string $token,
        string $aesKey
    ) {
        $this->corpId = $corpId;
        $this->providerSecret = $providerSecret;
        $this->suiteId = $suiteId;
        $this->suiteSecret = $suiteSecret;
        $this->token = $token;
        $this->aesKey = $aesKey;
    }

    public function getCorpId(): string
    {
        return $this->corpId;
    }

    public function getProviderSecret(): string
    {
        return $this->providerSecret;
    }

    public function getSuiteId(): string
    {
        return $this->suiteId;
    }

    public function getSuiteSecret(): string
    {
        return $this->suiteSecret;
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
