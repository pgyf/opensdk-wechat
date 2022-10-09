<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenWork;

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
use function func_get_args;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Server implements ServerInterface
{
    use InteractWithHandlers;
    use RespondXmlMessage;
    use DecryptXmlMessage;

    /**
     * @var ServerRequestInterface
     */
    protected $request;

    /**
     * @var Closure|null
     */
    protected $defaultSuiteTicketHandler = null;

    /**
     * @var Encryptor
     */
    protected $encryptor;

    /**
     * @var Encryptor
     */
    protected $providerEncryptor;


    /**
     * @throws \Throwable
     */
    public function __construct(
        Encryptor $encryptor,
        Encryptor $providerEncryptor,
        ServerRequestInterface $request = null
    ) {
        $this->encryptor = $encryptor;
        $this->providerEncryptor = $providerEncryptor;
        $this->request = $request ?? RequestUtil::createDefaultServerRequest();
    }

    /**
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @throws RuntimeException
     */
    public function serve(): ResponseInterface
    {
        $query = $this->request->getQueryParams();

        if ((bool) ($str = $query['echostr'] ?? '')) {
            $response = $this->providerEncryptor->decrypt(
                $str,
                $query['msg_signature'] ?? '',
                $query['nonce'] ?? '',
                $query['timestamp'] ?? ''
            );

            return new Response(200, [], $response);
        }

        $message = $this->getRequestMessage($this->request);

        $this->prepend($this->decryptRequestMessage());

        $response = $this->handle(new Response(200, [], 'success'), $message);

        if (! ($response instanceof ResponseInterface)) {
            $response = $this->transformToReply($response, $message, $this->encryptor);
        }

        return ServerResponse::make($response);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function withDefaultSuiteTicketHandler(callable $handler): void
    {
        //$this->defaultSuiteTicketHandler = fn (): mixed => $handler(...func_get_args());
        $this->defaultSuiteTicketHandler = function () use ($handler) {return $handler(...func_get_args());};
        $this->handleSuiteTicketRefreshed($this->defaultSuiteTicketHandler);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function handleSuiteTicketRefreshed(callable $handler): self
    {
        if ($this->defaultSuiteTicketHandler) {
            $this->withoutHandler($this->defaultSuiteTicketHandler);
        }

        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->InfoType === 'suite_ticket' ? $handler($message, $next) : $next($message);
        });

        return $this;
    }

    public function handleAuthCreated(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->InfoType === 'create_auth' ? $handler($message, $next) : $next($message);
        });

        return $this;
    }

    public function handleAuthChanged(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->InfoType === 'change_auth' ? $handler($message, $next) : $next($message);
        });

        return $this;
    }

    public function handleAuthCancelled(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->InfoType === 'cancel_auth' ? $handler($message, $next) : $next($message);
        });

        return $this;
    }

    public function handleUserCreated(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->InfoType === 'change_contact' && $message->ChangeType === 'create_user' ? $handler(
                $message,
                $next
            ) : $next($message);
        });

        return $this;
    }

    public function handleUserUpdated(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->InfoType === 'change_contact' && $message->ChangeType === 'update_user' ? $handler(
                $message,
                $next
            ) : $next($message);
        });

        return $this;
    }

    public function handleUserDeleted(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->InfoType === 'change_contact' && $message->ChangeType === 'delete_user' ? $handler(
                $message,
                $next
            ) : $next($message);
        });

        return $this;
    }

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

    public function handleUserTagUpdated(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->InfoType === 'change_contact' && $message->ChangeType === 'update_tag' ? $handler(
                $message,
                $next
            ) : $next($message);
        });

        return $this;
    }

    public function handleShareAgentChanged(callable $handler): self
    {
        $this->with(function (Message $message, Closure $next) use ($handler): mixed {
            return $message->InfoType === 'share_agent_change' ? $handler($message, $next) : $next($message);
        });

        return $this;
    }

    protected function decryptRequestMessage(): Closure
    {
        $query = $this->request->getQueryParams();

        return function (Message $message, Closure $next) use ($query): mixed {
            $this->decryptMessage(
                $message,
                $this->encryptor,
                $query['msg_signature'],
                $query['timestamp'],
                $query['nonce']
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
