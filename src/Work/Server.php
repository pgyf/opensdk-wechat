<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\Work;

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
    use DecryptXmlMessage;
    use RespondXmlMessage;
    use InteractWithHandlers;

    /**
     * @var ServerRequestInterface
     */
    protected $request;


    /**
     * @var Encryptor
     */
    protected $encryptor;

    /**
     * @throws Throwable
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
     * @throws RuntimeException|BadRequestException|Throwable
     */
    public function serve(): ResponseInterface
    {
        $query = $this->request->getQueryParams();

        if (! empty($query['echostr'])) {
            $response = $this->encryptor->decrypt(
                $query['echostr'],
                $query['msg_signature'] ?? '',
                $query['nonce'] ?? '',
                $query['timestamp'] ?? ''
            );

            return new Response(200, [], $response);
        }

        $message = $this->getRequestMessage($this->request);

        $this->prepend($this->decryptRequestMessage());

        $response = $this->handle(new Response(200, [], 'SUCCESS'), $message);

        if (! ($response instanceof ResponseInterface)) {
            $response = $this->transformToReply($response, $message, $this->encryptor);
        }

        return ServerResponse::make($response);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handleContactChanged(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->Event === 'change_contact' ? $handler($message, $next) : $next($message);
        });

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handleUserTagUpdated(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->Event === 'change_contact' && $message->ChangeType === 'update_tag' ? $handler(
                $message,
                $next
            ) : $next($message);
        });

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handleUserCreated(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->Event === 'change_contact' && $message->ChangeType === 'create_user' ? $handler(
                $message,
                $next
            ) : $next($message);
        });

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handleUserUpdated(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->Event === 'change_contact' && $message->ChangeType === 'update_user' ? $handler(
                $message,
                $next
            ) : $next($message);
        });

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handleUserDeleted(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->Event === 'change_contact' && $message->ChangeType === 'delete_user' ? $handler(
                $message,
                $next
            ) : $next($message);
        });

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handlePartyCreated(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->InfoType === 'change_contact' && $message->ChangeType === 'create_party' ? $handler(
                $message,
                $next
            ) : $next($message);
        });

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handlePartyUpdated(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->InfoType === 'change_contact' && $message->ChangeType === 'update_party' ? $handler(
                $message,
                $next
            ) : $next($message);
        });

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handlePartyDeleted(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->InfoType === 'change_contact' && $message->ChangeType === 'delete_party' ? $handler(
                $message,
                $next
            ) : $next($message);
        });

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handleBatchJobsFinished(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->Event === 'batch_job_result' ? $handler($message, $next) : $next($message);
        });

        return $this;
    }

    /**
     * @throws Throwable
     */
    public function addMessageListener(string $type, callable $handler): self
    {
        $this->withHandler(
            function (Message $message, Closure $next) use ($type, $handler): mixed {
                return $message->MsgType === $type ? $handler($message, $next) : $next($message);
            }
        );

        return $this;
    }

    /**
     * @throws Throwable
     */
    public function addEventListener(string $event, callable $handler): self
    {
        $this->withHandler(
            function (Message $message, Closure $next) use ($event, $handler): mixed {
                return $message->Event === $event ? $handler($message, $next) : $next($message);
            }
        );

        return $this;
    }

    protected function validateUrl(): Closure
    {
        return function (Message $message, Closure $next): Response {
            $query = $this->request->getQueryParams();
            $response = $this->encryptor->decrypt(
                $query['echostr'],
                $query['msg_signature'] ?? '',
                $query['nonce'] ?? '',
                $query['timestamp'] ?? ''
            );

            return new Response(200, [], $response);
        };
    }

    protected function decryptRequestMessage(): Closure
    {
        return function (Message $message, Closure $next): mixed {
            $query = $this->request->getQueryParams();
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

        return $this->decryptMessage(
            $message,
            $this->encryptor,
            $query['msg_signature'] ?? '',
            $query['timestamp'] ?? '',
            $query['nonce'] ?? ''
        );
    }
}
