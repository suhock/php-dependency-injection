<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Throwable;

/**
 * Exception that indicates the container encountered an error while attempting to resolve a class name to a value.
 *
 * @template TClass of object
 */
class ClassResolutionException extends ContainerException
{
    /**
     * @inheritDoc
     *
     * @param class-string<TClass> $className The name of the class that the container could not resolve
     * @param Throwable|null $previous [optional] The previous throwable used for exception chaining. If the throwable
     * is an instance of {@see DependencyInjectionException} then its content will be consolidated into the new
     * instance.
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly string $className,
        ?Throwable $previous = null
    ) {
        parent::__construct("The container encountered an error while resolving a value for $className", $previous);
    }

    /**
     * @return class-string<TClass> The name of the class the container could not resolve
     * @psalm-mutation-free
     */
    public function getClassName(): string
    {
        return $this->className;
    }
}
