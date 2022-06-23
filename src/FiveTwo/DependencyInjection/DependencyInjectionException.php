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

use LogicException;
use Throwable;

/**
 * Exception that indicates an error occurred in the dependency injection library.
 */
abstract class DependencyInjectionException extends LogicException
{
    protected ?DependencyInjectionException $consolidatedException = null;

    /**
     * @inheritDoc
     *
     * @param string $message [optional] The Exception message to throw.
     * @param Throwable|null $previous [optional] The previous throwable used for exception chaining. If the throwable
     * is of type {@see DependencyInjectionException} then its message and previous exception will be consolidated into
     * the new instance.
     */
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        if ($previous instanceof DependencyInjectionException) {
            parent::__construct(
                ($message !== '' ? "$message\n=> " : '') . $previous->getMessage(),
                previous: $previous->getPrevious()
            );
            $this->consolidatedException = $previous;
        } else {
            parent::__construct($message, previous: $previous);
        }
    }

    /**
     * @return DependencyInjectionException|null The {@see DependencyInjectionException} that was passed in as previous,
     * but was consolidated into this instance, or <code>null</code>
     */
    public function getConsolidatedException(): ?DependencyInjectionException
    {
        return $this->consolidatedException;
    }
}
