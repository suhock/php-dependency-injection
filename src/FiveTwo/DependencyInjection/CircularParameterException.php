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
 * Exception that indicates the dependency could not be resolved because it eventually depends on itself.
 *
 * @template TClass of object
 * @template-extends CircularDependencyException<TClass>
 */
class CircularParameterException extends CircularDependencyException
{
    /**
     * @inheritDoc
     *
     * @param string $functionName The name of the function with the dependency
     * @param string $parameterName The name of the parameter that could not be resolved
     * @param class-string<TClass> $className The name of the class that could not be resolved
     * @param Throwable|null $previous [optional] The previous throwable used for exception chaining. If the throwable
     * is an instance of {@see DependencyInjectionException} then its content will be consolidated into the new
     * instance.
     */
    public function __construct(
        private readonly string $functionName,
        private readonly string $parameterName,
        string $className,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $className,
            "\$$parameterName in $functionName()",
            $previous
        );
    }

    /**
     * @return string
     */
    public function getFunctionName(): string
    {
        return $this->functionName;
    }

    /**
     * @return string
     */
    public function getParameterName(): string
    {
        return $this->parameterName;
    }
}
