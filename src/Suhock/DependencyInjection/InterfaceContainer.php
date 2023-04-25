<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

/**
 * Provides instances of classes that inherit from the given interface or base class.
 *
 * @template TInterface of object
 */
class InterfaceContainer extends AbstractFactoryContainer
{
    /**
     * @param class-string<TInterface> $interfaceName The fully qualified name of the interface or base class
     * @param InjectorInterface|null $injector [optional] The injector to use for calling the factory method
     * @param callable|null $factory [optional] A factory to use for acquiring instances of classes. The first argument
     * will be the name of the class. Additional arguments can be provided from this container's {@see Injector}. If no
     * factory is provided, a default factory that directly instantiates the class will be used.
     * <code>
     * function&lt;TClass of TInterface&gt;(class-string&lt;TClass&gt; $className, [object ...]): TClass
     * </code>
     *
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly string $interfaceName,
        ?InjectorInterface $injector = null,
        ?callable $factory = null
    ) {
        parent::__construct($injector, $factory);
    }

    /**
     * @param class-string $className The name of the class to test
     *
     * @return bool <code>true</code> if the container can provide a value, <code>false</code> otherwise
     * @psalm-mutation-free
     */
    public function has(string $className): bool
    {
        return is_subclass_of($className, $this->interfaceName);
    }
}
