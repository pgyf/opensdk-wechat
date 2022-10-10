<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenWork;

use Pgyf\Opensdk\Kernel\Exceptions\RuntimeException;
use Pgyf\Opensdk\Wechat\OpenWork\Contracts\SuiteTicket as SuiteTicketInterface;
use function is_string;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use function sprintf;
// use Pgyf\Opensdk\Kernel\Symfony\Component\Cache\Adapter\FilesystemAdapter;
// use Pgyf\Opensdk\Kernel\Symfony\Component\Cache\Psr16Cache;

class SuiteTicket implements SuiteTicketInterface
{
    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var string
     */
    protected $suiteId;

    /**
     * @var string|null
     */
    protected $key = null;

    public function __construct(
        string $suiteId,
        ?CacheInterface $cache = null,
        ?string $key = null
    ) {
        $this->suiteId = $suiteId;
        $this->key = $key;
        //$this->cache = $cache ?? new Psr16Cache(new FilesystemAdapter(namespace: 'easywechat', defaultLifetime: 1500));
        if(!empty($cache)){
            $this->cache = $cache;
        }
    }

    public function getKey(): string
    {
        return $this->key ?? $this->key = sprintf('wechat.open_work.suite_ticket.%s', $this->suiteId);
    }

    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    /**
     * 
     * @return self
     * 
     * @throws InvalidArgumentException
     */
    public function setTicket(string $ticket)
    {
        $this->cache->set($this->getKey(), $ticket, 6000);

        return $this;
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getTicket(): string
    {
        $ticket = $this->cache->get($this->getKey());

        if (! $ticket || ! is_string($ticket)) {
            throw new RuntimeException('No suite_ticket found.');
        }

        return $ticket;
    }
}
