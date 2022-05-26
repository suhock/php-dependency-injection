<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use LogicException;
use Throwable;

class DependencyInjectionException extends LogicException
{
    /**
     * @param string $message
     * @param Throwable|null $previous
     */
    public function __construct(string $message, ?Throwable $previous = null)
    {
        if ($previous instanceof DependencyInjectionException) {
            $message = $message !== '' ? "$message\n=> " : '';
            $message .= $previous->getMessage();
            $previous = null;
        }

        parent::__construct($message, previous: $previous);
    }
}
