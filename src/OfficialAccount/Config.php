<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OfficialAccount;

class Config extends \Pgyf\Opensdk\Kernel\Config
{
    /**
     * @var array<string>
     */
    protected $requiredKeys = [
        'app_id',
    ];
}
