<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenPlatform;

use Pgyf\Opensdk\Kernel\Exceptions\RuntimeException;
use Pgyf\Opensdk\Wechat\OpenPlatform\Contracts\VerifyTicket as VerifyTicketInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

use function is_string;
use function sprintf;

class VerifyTicket implements VerifyTicketInterface
{
    /**
     * @var string
     */
    protected $appId = '';

    /**
     * @var string
     */
    protected $key = null;

    /**
     * @var CacheInterface
     */
    protected $cache = null;

    public function __construct(
        string $appId,
        ?string $key = null,
        ?CacheInterface $cache = null
    ) {
        $this->appId = $appId;
        $this->key = $key;
        $this->cache = $cache;
    }

    public function getKey(): string
    {
        return $this->key ?? $this->key = sprintf('wechat.open_platform.verify_ticket.%s', $this->appId);
    }

    /**
     * @param string $key
     * @return static
     */
    public function setKey(string $key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @param string $ticket
     * @return static
     * @throws InvalidArgumentException
     */
    public function setTicket(string $ticket)
    {
        $this->cache->set($this->getKey(), $ticket, (6000 - 100));

        return $this;
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getTicket(): string
    {
        $ticket = $this->cache->get($this->getKey());

        if (!$ticket || !is_string($ticket)) {
            throw new RuntimeException('No component_verify_ticket found.');
        }

        return $ticket;
    }
}
