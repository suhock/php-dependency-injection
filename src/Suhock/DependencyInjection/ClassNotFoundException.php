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
 * Exception that indicates the container does not contain the specified class.
 *
 * @template TClass of object
 */
class ClassNotFoundException extends ContainerException
{
    /**
     * @inheritDoc
     *
     * @param class-string<TClass> $className The name of the class that is not in the container
     * @param Throwable|null $previous [optional] The previous throwable used for exception chaining. If the throwable
     * is an instance of {@see DependencyInjectionException} then its content will be consolidated into the new
     * instance.
     */
    public function __construct(
        private readonly string $className,
        ?Throwable $previous = null
    ) {
        parent::__construct("The container does not contain the class $className", $previous);
    }

    /**
     * @return class-string<TClass> The name of the class that is not in the container
     */
    public function getClassName(): string
    {
        return $this->className;
    }
}
