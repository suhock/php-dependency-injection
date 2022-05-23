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
     * @param class-string<TDependency> $expectedClassName
     * @param mixed $actualValue
     * @param Throwable|null $previous
     */
    public function __construct(
        private readonly string $expectedClassName,
        private readonly mixed $actualValue,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            "Expected object of type $this->expectedClassName, got " . (
                is_object($actualValue) ?
                    "object of type " . get_class($actualValue) :
                    gettype($this->actualValue)
                ),
            $previous
        );
    }

    /**
     * @return class-string<TDependency>
     */
    public function getExpectedClassName(): string
    {
        return $this->expectedClassName;
    }

    /**
     * @return mixed
     */
    public function getActualValue(): mixed
    {
        return $this->actualValue;
    }
}
