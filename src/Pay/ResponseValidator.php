<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\Pay;

use function base64_decode;
use Pgyf\Opensdk\Kernel\Exceptions\BadResponseException;
use Pgyf\Opensdk\Kernel\Exceptions\InvalidConfigException;
use Pgyf\Opensdk\Wechat\Pay\Contracts\Merchant as MerchantInterface;
use const OPENSSL_ALGO_SHA256;
use Psr\Http\Message\ResponseInterface;
use function strval;

class ResponseValidator implements \Pgyf\Opensdk\Wechat\Pay\Contracts\ResponseValidator
{
    public const  MAX_ALLOWED_CLOCK_OFFSET = 300;

    public const  HEADER_TIMESTAMP = 'Wechatpay-Timestamp';

    public const  HEADER_NONCE = 'Wechatpay-Nonce';

    public const  HEADER_SERIAL = 'Wechatpay-Serial';

    public const  HEADER_SIGNATURE = 'Wechatpay-Signature';

    /**
     * @var MerchantInterface
     */
    protected $merchant;

    public function __construct(MerchantInterface $merchant)
    {
        $this->merchant = $merchant;
    }

    /**
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\BadResponseException
     * @throws \Pgyf\Opensdk\Kernel\Exceptions\InvalidConfigException
     */
    public function validate(ResponseInterface $response): void
    {
        if ($response->getStatusCode() !== 200) {
            throw new BadResponseException('Request Failed');
        }

        foreach ([self::HEADER_SIGNATURE, self::HEADER_TIMESTAMP, self::HEADER_SERIAL, self::HEADER_NONCE] as $header) {
            if (! $response->hasHeader($header)) {
                throw new BadResponseException("Missing Header: {$header}");
            }
        }

        [$timestamp] = $response->getHeader(self::HEADER_TIMESTAMP);
        [$nonce] = $response->getHeader(self::HEADER_NONCE);
        [$serial] = $response->getHeader(self::HEADER_SERIAL);
        [$signature] = $response->getHeader(self::HEADER_SIGNATURE);

        $body = (string) $response->getBody();

        $message = "{$timestamp}\n{$nonce}\n{$body}\n";

        if (\time() - \intval($timestamp) > self::MAX_ALLOWED_CLOCK_OFFSET) {
            throw new BadResponseException('Clock Offset Exceeded');
        }

        $publicKey = $this->merchant->getPlatformCert($serial);

        if (! $publicKey) {
            throw new InvalidConfigException(
                "No platform certs found for serial: {$serial}, 
                please download from wechat pay and set it in merchant config with key `certs`."
            );
        }

        if (false === \openssl_verify(
            $message,
            base64_decode($signature),
            strval($publicKey),
            OPENSSL_ALGO_SHA256
        )) {
            throw new BadResponseException('Invalid Signature');
        }
    }
}
