<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Throwable;

/**
 * Exception that indicates an error specific to a container occurred (i.e. while building or retrieving instances from
 * the container).
 */
class ContainerException extends DependencyInjectionException
{
    /**
     * @inheritDoc
     *
     * @param string $message [optional] The Exception message to throw.
     * @param Throwable|null $previous [optional] The previous throwable used for exception chaining. If the throwable
     * is an instance of {@see DependencyInjectionException} then its content will be consolidated into the new
     * instance.
     */
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
