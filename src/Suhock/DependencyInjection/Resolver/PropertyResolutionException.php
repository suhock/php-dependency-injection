<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

use ReflectionProperty;
use Suhock\DependencyInjection\InjectorException;
use Throwable;

/**
 * Exception that indicates the injector could not resolve a value for an injected property.
 */
final class PropertyResolutionException extends InjectorException
{
    /**
     * @param ReflectionProperty $reflectionProperty The unresolved property
     * @param Throwable|null $previous [optional] The previous throwable used for exception chaining. If the throwable
     * is an instance of {@see DependencyInjectionException} then its content will be consolidated into the new
     * instance.
     */
    public function __construct(
        private readonly ReflectionProperty $reflectionProperty,
        ?Throwable $previous = null
    ) {
        $className = $reflectionProperty->getDeclaringClass()->getName();

        parent::__construct(
            "Could not provide a value for property $className::\$" . $reflectionProperty->getName(),
            $previous
        );
    }

    public function getReflectionProperty(): ReflectionProperty
    {
        return $this->reflectionProperty;
    }
}
