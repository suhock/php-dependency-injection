<?php

/*
 * Copyright (c) 2023-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\InstanceProvider;

use Closure;

use function class_exists;
use function interface_exists;
use function is_callable;
use function is_string;

/**
 * Creates {@see InstanceProviderInterface} instances for the container's convenience builder methods.
 *
 * @internal
 */
final class InstanceProviderFactory
{
    /**
     * Chooses the provider a source describes with the following priority:
     * - null: class instance provider
     * - class/interface name or non-callable string: implementation provider
     * - callable string, callable array, Closure, or invokable object of different type: a closure provider
     * - any other object: an object instance provider
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param class-string<TClass>|TClass|callable|null $source
     *
     * @return InstanceProviderInterface<TClass>
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public static function createInstanceProvider(
        string $className,
        string|callable|object|null $source = null,
    ): InstanceProviderInterface {
        if ($source === null) {
            return self::createClassInstanceProvider($className);
        }

        if (is_string($source) && (class_exists($source) || interface_exists($source) || !is_callable($source))) {
            /** @var class-string<TClass> $source */
            return self::createImplementationInstanceProvider($className, $source);
        }

        if (is_callable($source) && !$source instanceof $className) {
            return self::createClosureInstanceProvider($className, $source);
        }

        return self::createObjectInstanceProvider($className, $source);
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     *
     * @return ClassInstanceProvider<TClass>
     */
    public static function createClassInstanceProvider(string $className): ClassInstanceProvider
    {
        return new ClassInstanceProvider($className);
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
        string $className,
        string $implementationClassName,
    ): ImplementationInstanceProvider {
        return new ImplementationInstanceProvider($className, $implementationClassName);
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
        object $object,
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
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public static function createClosureInstanceProvider(
        string $className,
        callable $closure,
    ): ClosureInstanceProvider {
        return new ClosureInstanceProvider($className, $closure(...));
    }
}
