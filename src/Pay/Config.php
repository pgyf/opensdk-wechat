<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\Pay;

class Config extends \Pgyf\Opensdk\Kernel\Config
{
    /**
     * @var array<string>
     */
    protected $requiredKeys = [
        'mch_id',
        'secret_key',
        'private_key',
        'certificate',
    ];
}
