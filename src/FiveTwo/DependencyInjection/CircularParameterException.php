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
 * @implements CircularExceptionInterface<TClass>
 */
class CircularParameterException extends InjectorException implements CircularExceptionInterface
{
    /**
     * @inheritDoc
     *
     * @param class-string<TClass> $className The class name of the dependency that could not be resolved due to a
     * circular dependency
     * @param string $functionName The name fully qualified name of the function or method with the circular dependency
     * @param string $parameterName The name of the parameter with the circular dependency
     * @param Throwable|null $previous [optional] The previous throwable used for exception chaining. If the throwable
     * is an instance of {@see DependencyInjectionException} then its content will be consolidated into the new
     * instance.
     */
    public function __construct(
        private readonly string $className,
        private readonly string $functionName,
        private readonly string $parameterName,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            "Circular dependency detected for $className \$$parameterName in $functionName()",
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

    /**
     * @return class-string<TClass>
     */
    public function getClassName(): string
    {
        return $this->className;
    }
}
