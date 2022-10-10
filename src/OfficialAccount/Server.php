<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OfficialAccount;

use Closure;
use Pgyf\Opensdk\Kernel\Contracts\Server as ServerInterface;
use Pgyf\Opensdk\Kernel\Encryptor;
use Pgyf\Opensdk\Kernel\Exceptions\BadRequestException;
use Pgyf\Opensdk\Kernel\Exceptions\InvalidArgumentException;
use Pgyf\Opensdk\Kernel\Exceptions\RuntimeException;
use Pgyf\Opensdk\Kernel\HttpClient\RequestUtil;
use Pgyf\Opensdk\Kernel\ServerResponse;
use Pgyf\Opensdk\Kernel\Traits\DecryptXmlMessage;
use Pgyf\Opensdk\Kernel\Traits\InteractWithHandlers;
use Pgyf\Opensdk\Kernel\Traits\RespondXmlMessage;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class Server implements ServerInterface
{
    use RespondXmlMessage;
    use DecryptXmlMessage;
    use InteractWithHandlers;

    /**
     * @var ServerRequestInterface
     */
    protected $request = null;

    /**
     * @var Encryptor
     */
    protected $encryptor = null;

    /**
     * @throws Throwable
     */
    public function __construct(
        $request = null,
        $encryptor = null
    ) {
        $this->request      = $request ?? RequestUtil::createDefaultServerRequest();
        $this->encryptor    = $encryptor;
    }

    /**
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @throws RuntimeException
     */
    public function serve(): ResponseInterface
    {
        if ((bool) ($str = $this->request->getQueryParams()['echostr'] ?? '')) {
            return new Response(200, [], $str);
        }

        $message = $this->getRequestMessage($this->request);
        $query = $this->request->getQueryParams();

        if ($this->encryptor && ! empty($query['msg_signature'])) {
            $this->prepend($this->decryptRequestMessage($query));
        }

        $response = $this->handle(new Response(200, [], 'success'), $message);

        if (! ($response instanceof ResponseInterface)) {
            $response = $this->transformToReply($response, $message, $this->encryptor);
        }

        return ServerResponse::make($response);
    }

    /**
     * @param string $type
     * @param callable|string $handler
     * @return self
     * @throws Throwable
     */
    public function addMessageListener(string $type, $handler): self
    {
        $handler = $this->makeClosure($handler);
        $this->withHandler(
            function (Message $message, Closure $next) use ($type, $handler) {
                return $message->MsgType === $type ? $handler($message, $next) : $next($message);
            }
        );

        return $this;
    }

    /**
     * @param string $event
     * @param callable|string $handler
     * @return self
     * @throws Throwable
     */
    public function addEventListener(string $event, $handler): self
    {
        $handler = $this->makeClosure($handler);
        $this->withHandler(
            function (Message $message, Closure $next) use ($event, $handler) {
                return $message->Event === $event ? $handler($message, $next) : $next($message);
            }
        );

        return $this;
    }

    /**
     * @param  array<string,string>  $query
     * @psalm-suppress PossiblyNullArgument
     */
    protected function decryptRequestMessage(array $query): Closure
    {
        return function (Message $message, Closure $next) use ($query) {
            if (! $this->encryptor) {
                return null;
            }

            $this->decryptMessage(
                $message,
                $this->encryptor,
                $query['msg_signature'] ?? '',
                $query['timestamp'] ?? '',
                $query['nonce'] ?? ''
            );

            return $next($message);
        };
    }

    /**
     * @throws BadRequestException
     */
    public function getRequestMessage(?ServerRequestInterface $request = null): \Pgyf\Opensdk\Kernel\Message
    {
        return Message::createFromRequest($request ?? $this->request);
    }

    /**
     * @throws BadRequestException
     * @throws RuntimeException
     */
    public function getDecryptedMessage(?ServerRequestInterface $request = null): \Pgyf\Opensdk\Kernel\Message
    {
        $request = $request ?? $this->request;
        $message = $this->getRequestMessage($request);
        $query = $request->getQueryParams();

        if (! $this->encryptor || empty($query['msg_signature'])) {
            return $message;
        }

        return $this->decryptMessage(
            $message,
            $this->encryptor,
            $query['msg_signature'],
            $query['timestamp'] ?? '',
            $query['nonce'] ?? ''
        );
    }
}
