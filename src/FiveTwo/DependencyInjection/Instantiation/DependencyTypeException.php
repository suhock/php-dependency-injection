<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use FiveTwo\DependencyInjection\DependencyInjectionException;
use Throwable;

/**
 * @template TDependency
 */
class DependencyTypeException extends DependencyInjectionException
{
    /**
     * @inheritDoc
     *
     * @param class-string<TDependency> $expectedClassName The name of the expected class
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
     * @return class-string<TDependency> The name of the expected class
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
