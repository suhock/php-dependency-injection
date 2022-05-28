<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use FiveTwo\DependencyInjection\DependencyInjectionException;
use FiveTwo\DependencyInjection\InjectorInterface;

/**
 * A factory for creating instances using a class's constructor.
 *
 * @template TDependency
 * @template-implements InstanceFactory<TDependency>
 */
class ClassInstanceFactory implements InstanceFactory
{
    /**
     * @param class-string<TDependency> $className The name of the class this factory will instantiate
     * @param InjectorInterface $injector The injector that will be used for instantiation
     */
    public function __construct(
        private readonly string $className,
        private readonly InjectorInterface $injector
    ) {
    }

    /**
     * @return TDependency A new instance of {@see $className}
     * @throws DependencyInjectionException If there was an error resolving values for the constructor parameters or
     * invoking the constructor
     */
    public function get(): object
    {
        return $this->injector->instantiate($this->className);
    }
}
