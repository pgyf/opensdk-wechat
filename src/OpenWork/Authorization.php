<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenWork;

use ArrayAccess;
use Pgyf\Opensdk\Kernel\Contracts\Arrayable;
use Pgyf\Opensdk\Kernel\Contracts\Jsonable;
use Pgyf\Opensdk\Kernel\Traits\HasAttributes;

/**
 * @implements ArrayAccess<string, mixed>
 */
class Authorization implements ArrayAccess, Jsonable, Arrayable
{
    use HasAttributes;

    public function getAppId(): string
    {
        /** @phpstan-ignore-next-line */
        return (string) $this->attributes['auth_corp_info']['corpid'];
    }
}
