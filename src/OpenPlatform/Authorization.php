<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenPlatform;

use ArrayAccess;
use Pgyf\Opensdk\Kernel\Contracts\Arrayable as ArrayableInterface;
use Pgyf\Opensdk\Kernel\Contracts\Jsonable as JsonableInterface;
use Pgyf\Opensdk\Kernel\Traits\HasAttributes;

/**
 * @implements ArrayAccess<string, mixed>
 */
class Authorization implements ArrayAccess, JsonableInterface, ArrayableInterface
{
    use HasAttributes;

    public function getAppId(): string
    {
        /** @phpstan-ignore-next-line */
        return (string) $this->attributes['authorization_info']['authorizer_appid'] ?? '';
    }

    public function getAccessToken(): AuthorizerAccessToken
    {
        return new AuthorizerAccessToken(
            /** @phpstan-ignore-next-line */
            $this->attributes['authorization_info']['authorizer_appid'] ?? '',

            /** @phpstan-ignore-next-line */
            $this->attributes['authorization_info']['authorizer_access_token'] ?? ''
        );
    }

    public function getRefreshToken(): string
    {
        /** @phpstan-ignore-next-line */
        return $this->attributes['authorization_info']['authorizer_refresh_token'] ?? '';
    }
}
