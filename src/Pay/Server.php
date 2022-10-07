<?php

namespace Pgyf\Opensdk\Wechat\Pay;

use Closure;
use Pgyf\Opensdk\Kernel\Contracts\Server as ServerInterface;
use Pgyf\Opensdk\Kernel\Exceptions\InvalidArgumentException;
use Pgyf\Opensdk\Kernel\Exceptions\RuntimeException;
use Pgyf\Opensdk\Kernel\HttpClient\RequestUtil;
use Pgyf\Opensdk\Kernel\ServerResponse;
use Pgyf\Opensdk\Kernel\Support\AesGcm;
use Pgyf\Opensdk\Kernel\Traits\InteractWithHandlers;
use Pgyf\Opensdk\Wechat\Pay\Contracts\Merchant as MerchantInterface;
use Exception;
use function is_array;
use function json_decode;
use function json_encode;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use function strval;
use Throwable;

/**
 * @link https://pay.weixin.qq.com/wiki/doc/apiv3/wechatpay/wechatpay4_1.shtml
 * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_5.shtml
 */
class Server implements ServerInterface
{
    use InteractWithHandlers;

    /**
     * @var ServerRequestInterface
     */
    protected $request = null;


    /**
     * @var MerchantInterface
     */
    protected $merchant;

    /**
     * @throws Throwable
     */
    public function __construct(
        MerchantInterface $merchant,
        ServerRequestInterface $request = null
    ) {
        $this->merchant = $merchant;
        $this->request = $request ?? RequestUtil::createDefaultServerRequest();
    }

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function serve(): ResponseInterface
    {
        $message = $this->getRequestMessage();

        try {
            $defaultResponse = new Response(
                200,
                [],
                strval(json_encode(['code' => 'SUCCESS', 'message' => '成功'], JSON_UNESCAPED_UNICODE))
            );
            $response = $this->handle($defaultResponse, $message);

            if (! ($response instanceof ResponseInterface)) {
                $response = $defaultResponse;
            }

            return ServerResponse::make($response);
        } catch (Exception $e) {
            return new Response(
                500,
                [],
                strval(json_encode(['code' => 'ERROR', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE))
            );
        }
    }

    /**
     * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_5.shtml
     *
     * @throws InvalidArgumentException
     */
    public function handlePaid(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->getEventType() === 'TRANSACTION.SUCCESS' && $message->trade_state === 'SUCCESS'
                ? $handler($message, $next) : $next($message);
        });

        return $this;
    }

    /**
     * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_11.shtml
     *
     * @throws InvalidArgumentException
     */
    public function handleRefunded(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return in_array($message->getEventType(), [
                'REFUND.SUCCESS',
                'REFUND.ABNORMAL',
                'REFUND.CLOSED',
            ]) ? $handler($message, $next) : $next($message);
        });

        return $this;
    }

    /**
     * @param ServerRequestInterface|null $request
     * @return \Pgyf\Opensdk\Kernel\Message|Message
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function getRequestMessage(ServerRequestInterface $request = null): Message
    {
        $originContent = (string) ($request ?? $this->request)->getBody();
        $attributes = json_decode($originContent, true);

        if (! is_array($attributes)) {
            throw new RuntimeException('Invalid request body.');
        }

        if (empty($attributes['resource']['ciphertext'])) {
            throw new RuntimeException('Invalid request.');
        }

        $attributes = json_decode(
            AesGcm::decrypt(
                $attributes['resource']['ciphertext'],
                $this->merchant->getSecretKey(),
                $attributes['resource']['nonce'],
                $attributes['resource']['associated_data'],
            ),
            true
        );

        if (! is_array($attributes)) {
            throw new RuntimeException('Failed to decrypt request message.');
        }

        return new Message($attributes, $originContent);
    }

    /**
     * @param ServerRequestInterface|null $request
     * @return \Pgyf\Opensdk\Kernel\Message|Message
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function getDecryptedMessage(ServerRequestInterface $request = null): Message
    {
        return $this->getRequestMessage($request);
    }
}
