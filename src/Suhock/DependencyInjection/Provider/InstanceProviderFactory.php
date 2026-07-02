<?php
/*
 * Copyright (c) 2023-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Provider;

use Closure;
use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\InjectorInterface;
use function is_string;

/**
 * Creates {@see InstanceProviderInterface} instances for the container's convenience builder methods.
 *
 * @internal
 */
final class InstanceProviderFactory
{
    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param class-string<TClass>|TClass|Closure|null $source
     *
     * @return InstanceProviderInterface<TClass>
     */
    public static function createInstanceProvider(
        InjectorInterface $injector,
        ContainerInterface $container,
        string $className,
        string|object|null $source = null
    ): InstanceProviderInterface {
        if ($source === null) {
            return self::createClassInstanceProvider($injector, $className);
        }

        if (is_string($source)) {
            return self::createImplementationInstanceProvider($container, $className, $source);
        }

        if (!$source instanceof Closure) {
            return self::createObjectInstanceProvider($className, $source);
        }

        if (ClassInstanceProvider::isMutator($source, $className)) {
            return self::createClassInstanceProvider($injector, $className, $source);
        }

        return self::createClosureInstanceProvider($injector, $className, $source);
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     *
     * @return ClassInstanceProvider<TClass>
     */
    public static function createClassInstanceProvider(
        InjectorInterface $injector,
        string $className,
        ?callable $mutator = null
    ): ClassInstanceProvider {
        return new ClassInstanceProvider($className, $injector, $mutator !== null ? $mutator(...) : null);
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param class-string<TClass> $implementationClassName
     *
     * @return ImplementationInstanceProvider<TClass>
     */
    public static function createImplementationInstanceProvider(
        ContainerInterface $container,
        string $className,
        string $implementationClassName
    ): ImplementationInstanceProvider {
        return new ImplementationInstanceProvider($className, $implementationClassName, $container);
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param TClass $object
     *
     * @return ObjectInstanceProvider<TClass>
     */
    public static function createObjectInstanceProvider(
        string $className,
        object $object
    ): ObjectInstanceProvider {
        return new ObjectInstanceProvider($className, $object);
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     *
     * @return ClosureInstanceProvider<TClass>
     */
    public static function createClosureInstanceProvider(
        InjectorInterface $injector,
        string $className,
        callable $closure
    ): ClosureInstanceProvider {
        return new ClosureInstanceProvider($className, $closure(...), $injector);
    }
}
