<?php

namespace Pgyf\Opensdk\Wechat\Work;

class Encryptor extends \Pgyf\Opensdk\Kernel\Encryptor
{
    public function __construct(string $corpId, string $token, string $aesKey)
    {
        parent::__construct($corpId, $token, $aesKey, null);
    }
}
