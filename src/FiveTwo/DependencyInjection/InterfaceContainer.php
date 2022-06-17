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

/**
 * Provides instances of classes that inherit from the given interface or base class.
 *
 * @template TInterface of object
 */
class InterfaceContainer extends AbstractFactoryContainer
{
    /**
     * @param class-string<TInterface> $interfaceName The name of the interface or base class
     * @param InjectorInterface|null $injector [optional] The injector to use for calling the factory
     * @param callable|null $factory [optional] A factory to use for acquiring instances of classes. The first argument
     * will be the name of the class. Additional arguments can be provided from this container's {@see Injector}. If no
     * factory is provided, a default factory that directly instantiates the class will be used.
     * <code>
     * function(class-string&lt;T implements TInterface&gt; $className, object...): null|TInterface
     * </code>
     */
    public function __construct(
        private readonly string $interfaceName,
        ?InjectorInterface $injector = null,
        ?callable $factory = null
    ) {
        parent::__construct($injector, $factory);
    }

    /**
     * @inheritDoc
     * @template TClass of TInterface
     *
     * @psalm-param class-string<TClass> $className
     *
     * @psalm-suppress DocblockTypeContradiction: Cannot resolve types for $className - docblock-defined type
     * class-string<TInterface:FiveTwo\DependencyInjection\ImplementationContainer as object> does not contain
     * class-string<TInterface>
     * Psalm has trouble resolving $className
     */
    public function has(string $className): bool
    {
        return is_subclass_of($className, $this->interfaceName);
    }
}
