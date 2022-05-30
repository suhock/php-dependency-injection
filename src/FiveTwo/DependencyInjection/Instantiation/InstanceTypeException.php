<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use FiveTwo\DependencyInjection\DependencyInjectionException;
use Throwable;

/**
 * Indicates that the type returned by an {@see InstanceFactory} is different than expected.
 *
 * @template TExpected of object
 */
class InstanceTypeException extends DependencyInjectionException
{
    /**
     * @inheritDoc
     *
     * @param class-string<TExpected> $expectedClassName The name of the expected class
     * @param mixed $actualValue The value actually received
     * @param null|Throwable $previous [optional] The previous throwable used for exception chaining. If the throwable
     * is an instance of {@see DependencyInjectionException} then its content will be consolidated into the new
     * instance.
     */
    public function __construct(
        private readonly string $expectedClassName,
        private readonly mixed $actualValue,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            "Expected object of type $this->expectedClassName, got " . (
                is_object($actualValue) ?
                    'object of type ' . get_class($actualValue) :
                    gettype($this->actualValue)
            ),
            $previous
        );
    }

    /**
     * @return class-string<TExpected> The name of the expected class
     */
    public function getExpectedClassName(): string
    {
        return $this->expectedClassName;
    }

    /**
     * @return mixed The value actually received
     */
    public function getActualValue(): mixed
    {
        return $this->actualValue;
    }
}
