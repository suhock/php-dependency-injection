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
     * @param class-string<TClass>|TClass|Closure(mixed...):TClass|null $source
     *
     * @return InstanceProviderInterface<TClass>
     */
    public static function createInstanceProvider(
        string $className,
        string|object|null $source = null,
    ): InstanceProviderInterface {
        if ($source === null) {
            return self::createClassInstanceProvider($className);
        }

        if (is_string($source)) {
            return self::createImplementationInstanceProvider($className, $source);
        }

        if ($source instanceof Closure) {
            return self::createClosureInstanceProvider($className, $source);
        }

        return self::createObjectInstanceProvider($className, $source);
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param callable(TClass,mixed...):mixed|null $mutator
     *
     * @return ClassInstanceProvider<TClass>
     */
    public static function createClassInstanceProvider(
        string $className,
        ?callable $mutator = null,
    ): ClassInstanceProvider {
        return new ClassInstanceProvider($className, $mutator !== null ? $mutator(...) : null);
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
     * @param callable(mixed...):TClass $closure
     *
     * @return ClosureInstanceProvider<TClass>
     */
    public static function createClosureInstanceProvider(
        string $className,
        callable $closure,
    ): ClosureInstanceProvider {
        return new ClosureInstanceProvider($className, $closure(...));
    }
}
