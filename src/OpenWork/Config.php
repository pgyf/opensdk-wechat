<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenWork;

class Config extends \Pgyf\Opensdk\Kernel\Config
{
    /**
     * @var array<string>
     */
    protected $requiredKeys = [
        'corp_id',
        'suite_id',
        'provider_secret',
        'suite_secret',
        'token',
        'aes_key',
    ];
}
