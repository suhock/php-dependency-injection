<?php
/*
 * Copyright (c) 2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Provision;

use Closure;
use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\InjectorInterface;

use function is_string;

final class InstanceProviderFactory
{
    public static function createInstanceProvider(
        InjectorInterface $injector,
        ContainerInterface $container,
        string $className,
        string|object|null $source = null
    ) {
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

    public static function createClassInstanceProvider(
        InjectorInterface $injector,
        string $className,
        ?callable $mutator = null
    ): ClassInstanceProvider {
        return new ClassInstanceProvider($className, $injector, $mutator);
    }

    public static function createImplementationInstanceProvider(
        ContainerInterface $container,
        string $className,
        string $implementationClassName
    ): ImplementationInstanceProvider {
        return new ImplementationInstanceProvider($className, $implementationClassName, $container);
    }

    public static function createObjectInstanceProvider(
        string $className,
        object $object
    ): ObjectInstanceProvider {
        return new ObjectInstanceProvider($className, $object);
    }

    public static function createClosureInstanceProvider(
        InjectorInterface $injector,
        string $className,
        callable $closure
    ): ClosureInstanceProvider {
        return new ClosureInstanceProvider($className, $closure, $injector);
    }
}
