<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Provision;

use Suhock\DependencyInjection\ContainerException;
use Throwable;

/**
 * Exception that indicates a class does not actually implement or extend the expected interface or base class.
 *
 * @template TExpected of object
 * @template TActual of object
 */
class ImplementationException extends ContainerException
{
    /**
     * @param class-string<TExpected> $expectedClassName The name of the expected base class
     * @param class-string<TActual> $actualClassName The name of the incorrect implementation class
     * @param null|Throwable $previous [optional] The previous throwable used for exception chaining
     */
    public function __construct(
        private readonly string $expectedClassName,
        private readonly string $actualClassName,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            "Class $this->actualClassName is not a subclass of $this->expectedClassName",
            $previous
        );
    }

    /**
     * @return class-string<TExpected> The name of the expected base class
     * @psalm-mutation-free
     */
    public function getExpectedClassName(): string
    {
        return $this->expectedClassName;
    }

    /**
     * @return class-string<TActual> The name of the incorrect implementation class
     * @psalm-mutation-free
     */
    public function getActualClassName(): string
    {
        return $this->actualClassName;
    }
}
