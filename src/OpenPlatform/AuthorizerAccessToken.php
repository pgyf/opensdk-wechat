<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenPlatform;

use Pgyf\Opensdk\Kernel\Contracts\AccessToken as AccessTokenInterface;

class AuthorizerAccessToken implements AccessTokenInterface
{
    /**
     * @var string
     */
    protected $appId;

    /**
     * @var string
     */
    protected $accessToken;

    public function __construct(string $appId, string $accessToken)
    {
        $this->appId = $appId;
        $this->accessToken = $accessToken;
    }

    public function getAppId(): string
    {
        return $this->appId;
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

    /**
     * @return array
     */
    public function toHeader(): array
    {
        return [];
    }

}
