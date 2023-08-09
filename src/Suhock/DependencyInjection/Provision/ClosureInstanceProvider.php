<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Provision;

use Closure;
use Suhock\DependencyInjection\DependencyInjectionException;
use Suhock\DependencyInjection\InjectorInterface;

/**
 * Factory that provides instances of a class by using a factory method.
 *
 * @template TClass of object
 * @template-implements InstanceProviderInterface<TClass>
 */
class ClosureInstanceProvider implements InstanceProviderInterface
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
     * @return TClass An instance of the class
     * @throws InstanceTypeException
     * @throws DependencyInjectionException
     */
    public function get(): object
    {
        $result = $this->injector->call($this->factory);

        if (!$result instanceof $this->className) {
            throw new InstanceTypeException($this->className, $result);
        }

        return $result;
    }
}
