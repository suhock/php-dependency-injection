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
 * Exception that indicates an instance could not be resolved in a container because it has a circular dependency.
 *
 * @template TClass of object
 * @implements CircularExceptionInterface<TClass>
 */
class CircularDependencyException extends ContainerException implements CircularExceptionInterface
{
    /**
     * @inheritDoc
     *
     * @param class-string<TClass> $className The class name of the dependency that could not be resolved due to a
     * circular dependency
     * @param Throwable|null $previous [optional] The previous throwable used for exception chaining. If the throwable
     * is an instance of {@see DependencyInjectionException} then its content will be consolidated into the new
     * instance.
     */
    public function __construct(
        private readonly string $className,
        ?Throwable $previous = null
    ) {
        parent::__construct("Circular dependency detected for class $className", $previous);
    }

    /**
     * @return class-string<TClass>
     */
    public function getClassName(): string
    {
        return $this->className;
    }
}
