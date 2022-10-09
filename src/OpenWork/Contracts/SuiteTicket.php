<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenWork\Contracts;

interface SuiteTicket
{
    public function getTicket(): string;

    /**
     * @param string $ticket
     * @return self
     */
    public function setTicket(string $ticket);
}
