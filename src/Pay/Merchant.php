<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\Pay;

use Pgyf\Opensdk\Kernel\Exceptions\InvalidArgumentException;
use Pgyf\Opensdk\Kernel\Exceptions\InvalidConfigException;
use Pgyf\Opensdk\Kernel\Support\PrivateKey;
use Pgyf\Opensdk\Kernel\Support\PublicKey;
use Pgyf\Opensdk\Wechat\Pay\Contracts\Merchant as MerchantInterface;
use Pgyf\Opensdk\Kernel\Support\Arr;

use function intval;
use function is_string;

class Merchant implements MerchantInterface
{
    /**
     * @var array<string, PublicKey>
     */
    protected $platformCerts = [];

    /**
     * @var int|string
     */
    protected $mchId;

    /**
     * @var PrivateKey
     */
    protected $privateKey;

    /**
     * @var PublicKey
     */
    protected $certificate;

    /**
     * @var string
     */
    protected $secretKey;

    /**
     * @var string|null
     */
    protected $v2SecretKey = null;


    /**
     * @param  array<int|string, string|PublicKey>  $platformCerts
     *
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     */
    public function __construct(
        $mchId,
        PrivateKey $privateKey,
        PublicKey $certificate,
        string $secretKey,
        string $v2SecretKey = null,
        array $platformCerts = []
    ) {
        $this->mchId        = $mchId;
        $this->privateKey   = $privateKey;
        $this->certificate  = $certificate;
        $this->secretKey    = $secretKey;
        $this->v2SecretKey  = $v2SecretKey;
        $this->platformCerts = $this->normalizePlatformCerts($platformCerts);
    }

    public function getMerchantId(): int
    {
        return intval($this->mchId);
    }

    public function getPrivateKey(): PrivateKey
    {
        return $this->privateKey;
    }

    public function getCertificate(): PublicKey
    {
        return $this->certificate;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    /**
     * @return string|null
     */
    public function getV2SecretKey()
    {
        return $this->v2SecretKey;
    }

    /**
     * Undocumented function
     * @param string $serial
     * @return PublicKey|null
     */
    public function getPlatformCert(string $serial)
    {
        return $this->platformCerts[$serial] ?? null;
    }

    /**
     * @param  array<array-key, string|PublicKey>  $platformCerts
     * @return array<string, PublicKey>
     *
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     */
    protected function normalizePlatformCerts(array $platformCerts): array
    {
        $certs = [];
        $isList = Arr::array_is_list($platformCerts);
        foreach ($platformCerts as $index => $publicKey) {
            if (is_string($publicKey)) {
                $publicKey = new PublicKey($publicKey);
            }

            if (! $publicKey instanceof PublicKey) {
                throw new InvalidArgumentException('Invalid platform certficate.');
            }

            $certs[$isList ? $publicKey->getSerialNo() : $index] = $publicKey;
        }

        return $certs;
    }
}
