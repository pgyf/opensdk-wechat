<?php

declare(strict_types=1);

namespace Pgyf\Opensdk\Wechat\Pay\Contracts;

use Pgyf\Opensdk\Kernel\Exceptions\BadResponseException;
use Psr\Http\Message\ResponseInterface;

interface ResponseValidator
{
    /**
     * @throws BadResponseException if the response is not successful.
     */
    public function validate(ResponseInterface $response): void;
}
