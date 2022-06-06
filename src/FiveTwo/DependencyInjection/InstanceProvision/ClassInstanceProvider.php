<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\InstanceProvision;

use Closure;
use FiveTwo\DependencyInjection\DependencyInjectionException;
use FiveTwo\DependencyInjection\InjectorInterface;

/**
 * Factory that provides instances of a class by directly instantiating the class.
 *
 * @template TClass of object
 * @template-implements InstanceProvider<TClass>
 */
class ClassInstanceProvider implements InstanceProvider
{
    /**
     * @param class-string<TClass> $className The name of the class this factory will instantiate
     * @param InjectorInterface $injector The injector that will be used for instantiation
     * @param null|Closure(TClass):void $mutator [Optional] Mutator function that allows additional changes to the
     * instantiated instance. The first parameter will be the new object instance. Any other parameters will be
     * injected.
     * @psalm-param null|Closure(TClass, mixed ...):void $mutator
     * @phpstan-param null|Closure(TClass, mixed ...):void $mutator
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
