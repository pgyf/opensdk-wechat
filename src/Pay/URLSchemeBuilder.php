<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\Pay;

use Pgyf\Opensdk\Kernel\Support\Str;
use Pgyf\Opensdk\Wechat\Pay\Contracts\Merchant as MerchantInterface;
use Exception;
use function sprintf;

class URLSchemeBuilder
{
    /**
     * @var MerchantInterface
     */
    protected $merchant;

    public function __construct(MerchantInterface $merchant)
    {
        $this->merchant = $merchant;
    }

    /**
     * @param string|int $productId
     * @param string $appId
     * @return string
     * @throws Exception
     */
    public function forProduct($productId, string $appId): string
    {
        $params = [
            'appid' => $appId,
            'mch_id' => $this->merchant->getMerchantId(),
            'time_stamp' => time(),
            'nonce_str' => Str::random(),
            'product_id' => $productId,
        ];

        $params['sign'] = (new LegacySignature($this->merchant))->sign($params);

        return 'weixin://wxpay/bizpayurl?'.http_build_query($params);
    }

    public function forCodeUrl(string $codeUrl): string
    {
        return sprintf('weixin://wxpay/bizpayurl?sr=%s', $codeUrl);
    }
}
