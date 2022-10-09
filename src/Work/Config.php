<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\Work;

class Config extends \Pgyf\Opensdk\Kernel\Config
{
    /**
     * @var array<string>
     */
    protected $requiredKeys = [
        'corp_id',
        'secret',
        'token',
        'aes_key',
    ];
}
