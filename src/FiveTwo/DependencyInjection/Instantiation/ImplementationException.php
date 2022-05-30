<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use FiveTwo\DependencyInjection\DependencyInjectionException;
use Throwable;

/**
 * Indicates that a class does not actually implement or extend an expected interface or base class.
 *
 * @template TExpected of object
 * @template TActual of object
 */
class ImplementationException extends DependencyInjectionException
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
     */
    public function getExpectedClassName(): string
    {
        return $this->expectedClassName;
    }

    /**
     * @return class-string<TActual> The name of the incorrect implementation class
     */
    public function getActualClassName(): string
    {
        return $this->actualClassName;
    }
}
