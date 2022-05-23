<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Throwable;

/**
 * @template TDependency
 */
class CircularDependencyException extends DependencyInjectionException
{
    /**
     * @param class-string<TDependency> $className
     * @param Throwable|null $previous
     */
    public function __construct(
        private readonly string $className,
        ?Throwable $previous = null
    ) {
        parent::__construct("Circular dependency detected for class $className", $previous);
    }

    /**
     * @return class-string<TDependency>
     */
    public function getClassName(): string
    {
        return $this->className;
    }
}
