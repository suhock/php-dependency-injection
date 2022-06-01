<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use Closure;
use FiveTwo\DependencyInjection\DependencyInjectionException;
use FiveTwo\DependencyInjection\InjectorInterface;

/**
 * Factory that provides instances of a class by using a factory method.
 *
 * @template TClass of object
 * @template-implements InstanceFactory<TClass>
 */
class ClosureInstanceFactory implements InstanceFactory
{
    /**
     * @param class-string<TClass> $className The name of the class this factory will provide
     * @param Closure $factory The factory that will be used for providing instances
     * @param InjectorInterface $injector The injector that will be used for invoking the factory method
     */
    public function __construct(
        private readonly string $className,
        private readonly Closure $factory,
        private readonly InjectorInterface $injector
    ) {
    }

    /**
     * @inheritDoc
     * @return TClass|null An instance of the class or <code>null</code>
     * @throws InstanceTypeException
     * @throws DependencyInjectionException
     */
    public function get(): ?object
    {
        $result = $this->injector->call($this->factory);

        if ($result !== null && !$result instanceof $this->className) {
            throw new InstanceTypeException($this->className, $result);
        }

        return $result;
    }
}
