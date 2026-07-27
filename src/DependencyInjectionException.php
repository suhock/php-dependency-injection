<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use RuntimeException;
use Throwable;

/**
 * Base class for every exception thrown by the dependency injection library. These signal errors in how the container
 * or injector was configured or used (a missing binding, a circular dependency, a service requested outside a scope,
 * a factory returning the wrong type), so they extend {@see RuntimeException} rather than {@see \LogicException}, which
 * is reserved for the library detecting a violation of its own internal invariants. Catch this to handle any
 * dependency injection failure.
 */
abstract class DependencyInjectionException extends RuntimeException
{
    protected ?DependencyInjectionException $consolidatedException = null;

    /**
     * @param string $message [optional] The Exception message to throw.
     * @param Throwable|null $previous [optional] The previous throwable used for exception chaining. If the throwable
     *     is a {@see DependencyInjectionException} then its message and previous exception will be consolidated into
     *     the new instance.
     */
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        if ($previous instanceof self) {
            parent::__construct(
                ($message !== '' ? "$message\n=> " : '') . $previous->getMessage(),
                previous: $previous->getPrevious(),
            );
            $this->consolidatedException = $previous;
        } else {
            parent::__construct($message, previous: $previous);
        }
    }

    /**
     * @return self|null The {@see DependencyInjectionException} that was passed in as previous, but was
     *     consolidated into this instance, or <code>null</code>
     */
    final public function getConsolidatedException(): ?self
    {
        return $this->consolidatedException;
    }
}
