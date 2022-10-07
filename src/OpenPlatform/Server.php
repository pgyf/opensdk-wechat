<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenPlatform;

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

use function func_get_args;

class Server implements ServerInterface
{
    use InteractWithHandlers;
    use RespondXmlMessage;
    use DecryptXmlMessage;

    /**
     * @var Closure
     */
    protected $defaultVerifyTicketHandler = null;

    /**
     * @var ServerRequestInterface
     */
    protected  $request;


    /**
     * @var Encryptor
     */
    protected $encryptor = null;


    /**
     * @throws \Throwable
     */
    public function __construct(
        Encryptor $encryptor,
        ServerRequestInterface $request = null
    ) {
        $this->encryptor = $encryptor;
        $this->request = $request ?? RequestUtil::createDefaultServerRequest();
    }

    /**
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @throws RuntimeException
     */
    public function serve(): ResponseInterface
    {
        if (!!($str = $this->request->getQueryParams()['echostr'] ?? '')) {
            return new Response(200, [], $str);
        }

        $message = $this->getRequestMessage($this->request);

        $this->prepend($this->decryptRequestMessage());

        $response = $this->handle(new Response(200, [], 'success'), $message);

        if (!($response instanceof ResponseInterface)) {
            $response = $this->transformToReply($response, $message, $this->encryptor);
        }

        return ServerResponse::make($response);
    }

    /**
     * @param callable $handler
     * @return static
     * @throws InvalidArgumentException
     */
    public function handleAuthorized(callable $handler)
    {
        $this->with(function (Message $message, Closure $next) use ($handler) {
            return $message->InfoType === 'authorized' ? $handler($message, $next) : $next($message);
        });

        return $this;
    }

    /**
     * @param callable $handler
     * @return static
     * @throws InvalidArgumentException
     */
    public function handleUnauthorized(callable $handler)
    {
        $this->with(function (Message $message, Closure $next) use ($handler) {
            return $message->InfoType === 'unauthorized' ? $handler($message, $next) : $next($message);
        });

        return $this;
    }

    /**
     * @param callable $handler
     * @return static
     * @throws InvalidArgumentException
     */
    public function handleAuthorizeUpdated(callable $handler)
    {
        $this->with(function (Message $message, Closure $next) use ($handler) {
            return $message->InfoType === 'updateauthorized' ? $handler($message, $next) : $next($message);
        });

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function withDefaultVerifyTicketHandler(callable $handler): void
    {
        //$this->defaultVerifyTicketHandler = fn (): mixed => $handler(...func_get_args());
        $this->defaultVerifyTicketHandler = function() use($handler){return $handler(...func_get_args());};
        $this->handleVerifyTicketRefreshed($this->defaultVerifyTicketHandler);
    }

    /**
     * @param callable $handler
     * @return static
     * @throws InvalidArgumentException
     */
    public function handleVerifyTicketRefreshed(callable $handler)
    {
        if ($this->defaultVerifyTicketHandler) {
            $this->withoutHandler($this->defaultVerifyTicketHandler);
        }

        $this->with(function (Message $message, Closure $next) use ($handler) {
            return $message->InfoType === 'component_verify_ticket' ? $handler($message, $next) : $next($message);
        });

        return $this;
    }

    protected function decryptRequestMessage(): Closure
    {
        $query = $this->request->getQueryParams();

        return function (Message $message, Closure $next) use ($query) {
            $message = $this->decryptMessage(
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

        return $this->decryptMessage(
            $message,
            $this->encryptor,
            $query['msg_signature'] ?? '',
            $query['timestamp'] ?? '',
            $query['nonce'] ?? ''
        );
    }
}
