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
 * Exception that indicates the injector could not resolve a value for a function parameter.
 */
class UnresolvedParameterException extends InjectorException
{
    /**
     * @inheritDoc
     *
     * @param string $functionName The name of the function requiring the parameter
     * @param string $parameterName The name of the unresolved parameter
     * @param string|null $parameterType [optional] The type of the unresolved parameter
     * @param Throwable|null $previous [optional] The previous throwable used for exception chaining. If the throwable
     * is an instance of {@see DependencyInjectionException} then its content will be consolidated into the new
     * instance.
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly string $functionName,
        private readonly string $parameterName,
        private readonly ?string $parameterType = null,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $parameterType !== null ?
                "Could not provide a value for required parameter $parameterType $parameterName in function " .
                    "$this->functionName()" :
                "Could not provide a value for required parameter $parameterName in function $this->functionName()",
            $previous
        );
    }

    /**
     * @return string The name of the function requiring the parameter
     * @psalm-mutation-free
     */
    public function getFunctionName(): string
    {
        return $this->functionName;
    }

    /**
     * @return string The name of the unresolved parameter
     * @psalm-mutation-free
     */
    public function getParameterName(): string
    {
        return $this->parameterName;
    }

    /**
     * @return string|null The type of the unresolved parameter, or <code>null</code> if none is specified
     * @psalm-mutation-free
     */
    public function getParameterType(): ?string
    {
        return $this->parameterType;
    }
}
