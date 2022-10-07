<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\OpenPlatform\Contracts;

interface VerifyTicket
{
    public function getTicket(): string;

    public function setTicket(string $ticket);
}
