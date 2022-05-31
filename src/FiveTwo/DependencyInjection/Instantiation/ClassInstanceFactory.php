<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use Closure;
use FiveTwo\DependencyInjection\DependencyInjectionException;
use FiveTwo\DependencyInjection\InjectorInterface;

/**
 * A factory for creating instances using a class's constructor.
 *
 * @template TClass of object
 * @template-implements InstanceFactory<TClass>
 */
class ClassInstanceFactory implements InstanceFactory
{
    /**
     * @param class-string<TClass> $className The name of the class this factory will instantiate
     * @param InjectorInterface $injector The injector that will be used for instantiation
     * @param null|Closure(TClass):void $mutator [Optional] Mutator function that allows additional changes to the
     * instantiated instance. The first parameter will be the new object instance. Any other parameters will be
     * injected.
     * @psalm-param null|Closure(TClass, mixed...):void $mutator
     * @phpstan-param null|Closure(TClass, mixed...):void $mutator
     */
    public function __construct(
        private readonly string $className,
        private readonly InjectorInterface $injector,
        private readonly ?Closure $mutator = null
    ) {
    }

    /**
     * @return TClass A new instance of {@see $className}
     * @throws DependencyInjectionException If there was an error resolving values for the constructor parameters or
     * invoking the constructor
     */
    public function get(): object
    {
        $instance = $this->injector->instantiate($this->className);

        if ($this->mutator !== null) {
            $this->injector->call($this->mutator, [$instance]);
        }

        return $instance;
    }
}
