<?php

namespace Pgyf\Opensdk\Wechat\OpenWork;

use Pgyf\Opensdk\Kernel\Encryptor;

class SuiteEncryptor extends Encryptor
{
    public function __construct(string $suiteId, string $token, string $aesKey)
    {
        parent::__construct($suiteId, $token, $aesKey, null);
    }
}
