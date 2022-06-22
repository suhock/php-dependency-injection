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
 */
class CircularDependencyException extends DependencyInjectionException
{
    /**
     * @inheritDoc
     *
     * @param class-string<TClass> $className The name of the class that could not be resolved
     * @param string $context [optional] The context in which the dependency could not be resolved
     * @param Throwable|null $previous [optional] The previous throwable used for exception chaining. If the throwable
     * is an instance of {@see DependencyInjectionException} then its content will be consolidated into the new
     * instance.
     */
    public function __construct(
        private readonly string $className,
        private readonly string $context = '',
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $context === '' ?
                "Circular dependency detected for class $className" :
                "Circular dependency detected for class $className in $context",
            $previous
        );
    }

    /**
     * @return class-string<TClass>
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @return string
     */
    public function getContext(): string
    {
        return $this->context;
    }
}
