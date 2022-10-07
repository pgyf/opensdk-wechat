<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\Pay\Contracts;

use Pgyf\Opensdk\Kernel\Support\PrivateKey;
use Pgyf\Opensdk\Kernel\Support\PublicKey;

interface Merchant
{
    public function getMerchantId(): int;

    public function getPrivateKey(): PrivateKey;

    public function getSecretKey(): string;

    /**
     * @return string|null
     */
    public function getV2SecretKey();

    public function getCertificate(): PublicKey;

    /**
     * @param string $serial
     * @return PublicKey|null
     */
    public function getPlatformCert(string $serial);
}
