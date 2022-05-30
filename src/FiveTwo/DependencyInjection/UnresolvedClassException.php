<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Throwable;

/**
 * Indicates that the container could not resolve a value for the specified class.
 *
 * @template TClass of object
 */
class UnresolvedClassException extends UnresolvedDependencyException
{
    /**
     * @inheritDoc
     *
     * @param class-string<TClass> $className The name of the class that could not be resolved
     * @param null|Throwable $previous [optional] The previous throwable used for exception chaining. If the throwable
     * is an instance of {@see DependencyInjectionException} then its content will be consolidated into the new
     * instance.
     */
    public function __construct(
        private readonly string $className,
        ?Throwable $previous = null
    ) {
        parent::__construct("Could not resolve a value for class $className", $previous);
    }

    /**
     * @return class-string<TClass> The name of the class that could not be resolved
     */
    public function getClassName(): string
    {
        return $this->className;
    }
}
