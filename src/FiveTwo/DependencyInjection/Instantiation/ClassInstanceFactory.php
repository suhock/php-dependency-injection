<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use FiveTwo\DependencyInjection\DependencyInjectionException;
use FiveTwo\DependencyInjection\InjectorInterface;

/**
 * @template TDependency
 * @template-implements InstanceFactory<TDependency>
 */
class ClassInstanceFactory implements InstanceFactory
{
    /**
     * @param class-string<TDependency> $className
     */
    public function __construct(
        private readonly string $className,
        private readonly InjectorInterface $injector
    ) {
    }

    /**
     * @return TDependency|null
     * @throws DependencyInjectionException
     */
    public function get(): ?object
    {
        return $this->injector->instantiate($this->className);
    }
}
