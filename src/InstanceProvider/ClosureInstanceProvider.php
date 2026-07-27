<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\InstanceProvider;

use Closure;

/**
 * Factory that provides instances of a class by using a factory method.
 *
 * @template TClass of object
 *
 * @template-implements InstanceProviderInterface<TClass>
 *
 * @internal
 */
final class ClosureInstanceProvider implements InstanceProviderInterface
{
    /**
     * @param class-string<TClass> $className The name of the class this factory will provide
     * @param Closure $factory The factory that will be used for providing instances
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function __construct(
        public readonly string $className,
        public readonly Closure $factory,
    ) {}

}
