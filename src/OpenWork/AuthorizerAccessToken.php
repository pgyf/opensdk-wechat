<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenWork;

use Pgyf\Opensdk\Kernel\Contracts\AccessToken;

class AuthorizerAccessToken implements AccessToken
{

    /**
     * @var string
     */
    protected $corpId;

    /**
     * @var string
     */
    protected $accessToken;


    public function __construct(string $corpId, string $accessToken)
    {
        $this->corpId = $corpId;
        $this->accessToken = $accessToken;
    }

    public function getCorpId(): string
    {
        return $this->corpId;
    }

    public function getToken(): string
    {
        return $this->accessToken;
    }

    public function __toString()
    {
        return $this->accessToken;
    }

    /**
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return ['access_token' => $this->getToken()];
    }

    public function toHeader(): array
    {
        return [];
    }
}
